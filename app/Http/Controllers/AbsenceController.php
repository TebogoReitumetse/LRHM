<?php

namespace App\Http\Controllers;

use App\Enums\AbsenceClassification;
use App\Enums\LeaveStatus;
use App\Models\AbsenceRecord;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Absence and late-arrival records for operational follow-up.
 * The system never classifies misconduct or starts disciplinary action:
 * any outcome is recorded by management after reviewing the circumstances.
 */
class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only('employee', 'classification', 'follow_up', 'type', 'from', 'to');
        $records = AbsenceRecord::with('user.department', 'recorder')
            ->when($f['employee'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['classification'] ?? null, fn ($q, $v) => $q->where('classification', $v))
            ->when($f['follow_up'] ?? null, fn ($q, $v) => $q->where('follow_up_status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('record_type', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->latest('date')->paginate(25)->withQueryString();

        return view('absences.index', [
            'records' => $records,
            'filters' => $f,
            'employees' => User::orderBy('name')->get(['id', 'name']),
            'leaveOnDate' => $this->approvedLeaveFor($records->getCollection()),
        ]);
    }

    public function create(Request $request)
    {
        return view('absences.form', [
            'record' => new AbsenceRecord(['date' => today(), 'record_type' => 'absence', 'follow_up_status' => 'open',
                'classification' => AbsenceClassification::PendingExplanation, 'user_id' => $request->integer('employee') ?: null]),
            'employees' => User::active()->orderBy('name')->get(['id', 'name', 'employee_number']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $record = AbsenceRecord::create($data + ['recorded_by' => $request->user()->id]);
        $this->handleUpload($request, $record);

        return redirect()->route('absences.show', $record)->with('success', 'Attendance record saved.');
    }

    public function show(AbsenceRecord $absence)
    {
        $absence->load('user.department', 'recorder', 'reviewer');

        return view('absences.show', [
            'record' => $absence,
            'leave' => $this->approvedLeaveFor(collect([$absence]))[$absence->id] ?? null,
        ]);
    }

    public function edit(AbsenceRecord $absence)
    {
        return view('absences.form', [
            'record' => $absence,
            'employees' => User::orderBy('name')->get(['id', 'name', 'employee_number']),
        ]);
    }

    public function update(Request $request, AbsenceRecord $absence)
    {
        $absence->update($this->validated($request, $absence));
        $this->handleUpload($request, $absence);

        return redirect()->route('absences.show', $absence)->with('success', 'Attendance record updated.');
    }

    public function document(AbsenceRecord $absence)
    {
        abort_unless($absence->document_path, 404);

        return Storage::disk('local')->download($absence->document_path, $absence->document_name);
    }

    private function validated(Request $request, ?AbsenceRecord $existing = null): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'record_type' => ['required', Rule::in(array_keys(AbsenceRecord::TYPES))],
            'arrival_time' => ['nullable', 'required_if:record_type,late_arrival', 'date_format:H:i'],
            'classification' => ['required', Rule::enum(AbsenceClassification::class)],
            'management_outcome' => ['nullable', 'required_if:classification,reviewed_classified', Rule::in(array_keys(AbsenceRecord::OUTCOMES))],
            'reason' => ['nullable', 'string', 'max:2000'],
            'management_comment' => ['nullable', 'required_if:classification,reviewed_classified', 'string', 'max:2000'],
            'follow_up_status' => ['required', Rule::in(array_keys(AbsenceRecord::FOLLOW_UP))],
            'document' => ['nullable', 'file', 'mimes:'.config('hrms.upload_mimes'), 'max:'.config('hrms.max_upload_kb')],
        ], [
            'management_outcome.required_if' => 'Record management\'s outcome when classifying a reviewed absence.',
            'management_comment.required_if' => 'Explain the circumstances considered when classifying the absence.',
        ]);
        unset($data['document']);

        if ($data['record_type'] !== 'late_arrival') {
            $data['arrival_time'] = null;
        }
        if ($data['classification'] !== AbsenceClassification::ReviewedClassified->value) {
            $data['management_outcome'] = null;
        } elseif (! $existing || $existing->management_outcome !== $data['management_outcome']) {
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        }

        return $data;
    }

    private function handleUpload(Request $request, AbsenceRecord $record): void
    {
        if ($file = $request->file('document')) {
            if ($record->document_path) {
                Storage::disk('local')->delete($record->document_path);
            }
            $record->update([
                'document_path' => $file->store('absences/'.$record->id, 'local'),
                'document_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    /** Map of absence record id => approved leave application covering that date. */
    private function approvedLeaveFor($records): array
    {
        $out = [];
        foreach ($records as $r) {
            $leave = LeaveApplication::where('user_id', $r->user_id)->status(LeaveStatus::Approved)
                ->overlapping($r->date, $r->date)->first();
            if ($leave) {
                $out[$r->id] = $leave;
            }
        }

        return $out;
    }
}
