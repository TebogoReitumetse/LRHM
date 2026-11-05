<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\LeaveDocument;
use App\Services\LeaveWorkflow;
use App\Services\WorkingDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveApplicationController extends Controller
{
    public function __construct(private WorkingDayCalculator $calendar, private LeaveWorkflow $workflow) {}

    /** The signed-in user's own applications. */
    public function index(Request $request)
    {
        $applications = $request->user()->leaveApplications()->with('category')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();

        return view('leave.index', compact('applications'));
    }

    public function create(Request $request)
    {
        return view('leave.form', [
            'application' => new LeaveApplication(['working_days' => 0]),
            'categories' => LeaveCategory::active()->get(),
            'user' => $request->user()->load('department'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request);

        $app = DB::transaction(function () use ($request, $user, $data) {
            $app = new LeaveApplication($data);
            $app->fill([
                'user_id' => $user->id,
                'employee_name' => $user->name,
                'employee_number' => $user->employee_number,
                'department' => $user->department?->name,
                'job_title' => $user->job_title,
                'status' => LeaveStatus::Draft,
            ]);
            $app->save();
            $app->logAction('created', null, LeaveStatus::Draft);
            $this->storeDocuments($request, $app);

            return $app;
        });

        return $this->afterSave($request, $app);
    }

    public function show(LeaveApplication $leave)
    {
        $this->authorize('view', $leave);
        $leave->load('category', 'user.department', 'documents.uploader', 'actions.user', 'manager', 'director');

        return view('leave.show', [
            'application' => $leave,
            'holidays' => $this->calendar->holidaysBetween($leave->start_date, $leave->end_date),
        ]);
    }

    public function edit(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);

        return view('leave.form', [
            'application' => $leave->load('documents'),
            'categories' => LeaveCategory::active()->get(),
            'user' => $request->user()->load('department'),
        ]);
    }

    public function update(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);
        $data = $this->validated($request, $leave);

        DB::transaction(function () use ($request, $leave, $data) {
            $leave->update($data);
            $this->storeDocuments($request, $leave);
        });

        return $this->afterSave($request, $leave);
    }

    public function destroy(LeaveApplication $leave)
    {
        $this->authorize('delete', $leave);
        foreach ($leave->documents as $doc) {
            Storage::disk('local')->delete($doc->path);
        }
        $leave->delete();

        return redirect()->route('leave.index')->with('success', 'Draft deleted.');
    }

    public function submit(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);
        $this->assertSubmittable($leave);
        $this->workflow->submit($leave);

        return redirect()->route('leave.show', $leave)->with('success', "Application {$leave->reference} submitted for review.");
    }

    public function print(LeaveApplication $leave)
    {
        $this->authorize('view', $leave);
        $leave->load('category', 'user.department', 'documents', 'actions.user', 'manager', 'director');

        return view('leave.print', ['application' => $leave]);
    }

    public function document(LeaveApplication $leave, LeaveDocument $document)
    {
        $this->authorize('view', $leave);
        abort_unless($document->leave_application_id === $leave->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(LeaveApplication $leave, LeaveDocument $document)
    {
        $this->authorize('update', $leave);
        abort_unless($document->leave_application_id === $leave->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /** AJAX: working-day calculation for the form. */
    public function calculate(Request $request)
    {
        $request->validate(['start' => 'required|date', 'end' => 'required|date']);
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        if ($end->lt($start)) {
            return response()->json(['days' => 0, 'error' => 'End date is before the start date.']);
        }

        return response()->json([
            'days' => $this->calendar->count($start, $end),
            'return_date' => $this->calendar->nextWorkingDay($end)->toDateString(),
            'holidays' => collect($this->calendar->holidaysBetween($start, $end))
                ->map(fn ($n, $d) => Carbon::parse($d)->format('D d M').' – '.$n)->values(),
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?LeaveApplication $existing = null): array
    {
        $category = LeaveCategory::find($request->input('leave_category_id'));
        $max = config('hrms.max_upload_kb');

        $data = $request->validate([
            'leave_category_id' => ['required', Rule::exists('leave_categories', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'return_date' => ['required', 'date', 'after:end_date'],
            'reason' => [$category?->requires_reason ? 'required' : 'nullable', 'string', 'max:2000'],
            'contact_while_away' => ['nullable', 'string', 'max:150'],
            'declaration' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:'.config('hrms.upload_mimes'), "max:$max"],
        ], [
            'return_date.after' => 'The expected return date must be after the last day of leave.',
            'reason.required' => 'Please give a reason. It is required for this leave category.',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = $this->calendar->count($start, $end);

        if ($days === 0) {
            throw ValidationException::withMessages(['end_date' => 'The selected period has no working days (weekends and public holidays are excluded).']);
        }

        // Prevent overlapping applications for the same employee.
        $overlap = LeaveApplication::where('user_id', $request->user()->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->whereNotIn('status', [LeaveStatus::Declined->value, LeaveStatus::Draft->value])
            ->overlapping($start, $end)->first();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => "These dates overlap your application {$overlap->reference} ({$overlap->status->label()})."]);
        }

        $declared = $request->boolean('declaration');

        return [
            'leave_category_id' => $data['leave_category_id'],
            'start_date' => $start,
            'end_date' => $end,
            'return_date' => $data['return_date'],
            'working_days' => $days,
            'reason' => $data['reason'] ?? null,
            'contact_while_away' => $data['contact_while_away'] ?? null,
            'declaration_accepted' => $declared,
            'declaration_accepted_at' => $declared ? now() : null,
        ];
    }

    private function storeDocuments(Request $request, LeaveApplication $app): void
    {
        foreach ($request->file('documents', []) as $file) {
            $app->documents()->create([
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('leave/'.$app->id, 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function assertSubmittable(LeaveApplication $app): void
    {
        $app->load('category', 'documents');
        $errors = [];
        if (! $app->declaration_accepted) {
            $errors['declaration'] = 'You must accept the employee declaration before submitting.';
        }
        if ($app->category->requires_document && $app->documents->isEmpty()) {
            $errors['documents'] = "A supporting document is required for {$app->category->name}.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function afterSave(Request $request, LeaveApplication $app)
    {
        if ($request->input('action') === 'submit') {
            try {
                $this->assertSubmittable($app);
            } catch (ValidationException $e) {
                return redirect()->route('leave.edit', $app)->withErrors($e->errors())
                    ->with('warning', 'Your application was saved as a draft but could not be submitted yet.');
            }
            $this->workflow->submit($app);

            return redirect()->route('leave.show', $app)->with('success', "Application {$app->reference} submitted. Your Manager has been notified.");
        }

        return redirect()->route('leave.show', $app)->with('success', 'Application saved as a draft.');
    }
}
