<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Support\Export;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public const TYPES = [
        'monthly' => ['Monthly leave summary', 'All leave falling in a selected month, summarised by category and status.'],
        'range' => ['Date-range leave report', 'Every application overlapping a chosen period.'],
        'employee' => ['Employee leave history', 'The full leave history of one employee.'],
        'department' => ['Departmental leave summary', 'Applications and approved days per department.'],
        'status' => ['Approved / declined / pending report', 'Applications grouped by their outcome.'],
    ];

    public function index()
    {
        return view('reports.index', [
            'types' => self::TYPES,
            'employees' => User::orderBy('name')->get(['id', 'name', 'employee_number']),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'month' => ['nullable', 'required_if:type,monthly', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'employee' => ['nullable', 'required_if:type,employee', 'exists:users,id'],
            'department' => ['nullable', 'exists:departments,id'],
            'status_group' => ['nullable', 'in:approved,declined,pending'],
            'format' => ['nullable', 'in:html,pdf,csv,print'],
        ]);

        [$title, $subtitle, $apps, $summary] = $this->build($data);
        $format = $data['format'] ?? 'html';
        $name = str($title)->slug().'-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            return Export::csv($name, LeaveRegisterController::headings(), $apps->map(fn ($a) => LeaveRegisterController::row($a)));
        }

        $payload = compact('title', 'subtitle', 'summary') + ['applications' => $apps];

        return match ($format) {
            'pdf' => Export::pdf('reports.document', $payload, $name),
            'print' => Export::print('reports.document', $payload),
            default => view('reports.show', $payload + ['query' => $request->except('format')]),
        };
    }

    private function build(array $d): array
    {
        $q = LeaveApplication::with('category', 'user.department', 'manager', 'director')->submittedOnly()
            ->when($d['department'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('department_id', $v)));

        $from = ! empty($d['from']) ? Carbon::parse($d['from']) : null;
        $to = ! empty($d['to']) ? Carbon::parse($d['to']) : null;
        $period = fn () => $from || $to ? ($from?->format('d M Y') ?? '…').' to '.($to?->format('d M Y') ?? '…') : 'All dates';
        $applyRange = fn ($q) => $from || $to ? $q->overlapping($from ?? '1900-01-01', $to ?? '2999-12-31') : $q;

        switch ($d['type']) {
            case 'monthly':
                $m = Carbon::createFromFormat('Y-m', $d['month'])->startOfMonth();
                $apps = $q->overlapping($m, $m->copy()->endOfMonth())->orderBy('start_date')->get();

                return ['Monthly Leave Summary', $m->format('F Y'), $apps, $this->summaries($apps, ['category', 'status'])];

            case 'range':
                $apps = $applyRange($q)->orderBy('start_date')->get();

                return ['Leave Report', $period(), $apps, $this->summaries($apps, ['category', 'status'])];

            case 'employee':
                $emp = User::with('department')->findOrFail($d['employee']);
                $apps = $applyRange($q->where('user_id', $emp->id))->orderByDesc('start_date')->get();

                return ["Leave History: {$emp->name}", "{$emp->employee_number} · ".($emp->department?->name ?? 'No department').' · '.$period(),
                    $apps, $this->summaries($apps, ['category', 'status'])];

            case 'department':
                $apps = $applyRange($q)->orderBy('department')->orderBy('start_date')->get();

                return ['Departmental Leave Summary', $period(), $apps, $this->summaries($apps, ['department', 'status'])];

            case 'status':
            default:
                $group = $d['status_group'] ?? null;
                $statuses = match ($group) {
                    'approved' => [LeaveStatus::Approved],
                    'declined' => [LeaveStatus::Declined],
                    'pending' => [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview, LeaveStatus::PendingDirectorApproval, LeaveStatus::ReturnedForInformation],
                    default => null,
                };
                $apps = $applyRange($q)->when($statuses, fn ($q) => $q->status(...$statuses))->orderBy('status')->orderBy('start_date')->get();
                $label = $group ? ucfirst($group).' applications' : 'All outcomes';

                return ['Application Status Report', $label.' · '.$period(), $apps, $this->summaries($apps, ['status'])];
        }
    }

    /** @return array<string, Collection> */
    private function summaries(Collection $apps, array $by): array
    {
        $out = [];
        foreach ($by as $dim) {
            $key = match ($dim) {
                'category' => fn ($a) => $a->category->name,
                'status' => fn ($a) => $a->status->label(),
                'department' => fn ($a) => $a->department ?: 'No department',
            };
            $out['By '.$dim] = $apps->groupBy($key)->map(fn ($g) => [
                'applications' => $g->count(),
                'employees' => $g->pluck('user_id')->unique()->count(),
                'days' => (float) $g->sum('working_days'),
                'approved_days' => (float) $g->where('status', LeaveStatus::Approved)->sum('working_days'),
            ])->sortKeys();
        }

        return $out;
    }
}
