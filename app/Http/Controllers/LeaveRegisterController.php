<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
use App\Support\Export;
use Illuminate\Http\Request;

/** Central electronic leave register (management only). */
class LeaveRegisterController extends Controller
{
    private const FILTERS = ['search', 'employee', 'department', 'category', 'status', 'from', 'to'];

    public function index(Request $request)
    {
        $filters = $request->only(self::FILTERS);

        return view('register.index', [
            'applications' => $this->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'employees' => User::orderBy('name')->get(['id', 'name', 'employee_number']),
            'departments' => Department::orderBy('name')->get(),
            'categories' => LeaveCategory::orderBy('sort_order')->get(),
            'statuses' => collect(LeaveStatus::options())->except(LeaveStatus::Draft->value),
        ]);
    }

    public function export(Request $request, string $format)
    {
        $filters = $request->only(self::FILTERS);
        $rows = $this->query($filters)->get();
        $name = 'leave-register-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            return Export::csv($name, self::headings(), $rows->map(fn ($a) => self::row($a)));
        }

        $data = ['title' => 'Leave Register', 'subtitle' => self::describe($filters), 'applications' => $rows, 'summary' => null];

        return $format === 'pdf'
            ? Export::pdf('reports.document', $data, $name)
            : Export::print('reports.document', $data);
    }

    private function query(array $filters)
    {
        return LeaveApplication::with('category', 'user.department', 'manager', 'director')
            ->submittedOnly()->filter($filters)->latest('submitted_at');
    }

    public static function headings(): array
    {
        return ['Reference', 'Employee', 'Employee no.', 'Department', 'Leave category', 'Start date', 'End date', 'Return date',
            'Working days', 'Submitted', 'Status', 'Manager', 'Manager recommendation', 'Manager comment',
            'Director', 'Director decision comment', 'Decision date & time'];
    }

    public static function row(LeaveApplication $a): array
    {
        return [
            $a->reference, $a->employee_name, $a->employee_number, $a->department, $a->category->name,
            $a->start_date->format('Y-m-d'), $a->end_date->format('Y-m-d'), $a->return_date->format('Y-m-d'),
            $a->working_days, $a->submitted_at?->format('Y-m-d H:i'), $a->status->label(),
            $a->manager?->name, $a->manager_recommendation ? ucfirst($a->manager_recommendation) : '', $a->manager_comment,
            $a->director?->name, $a->director_comment, $a->decided_at?->format('Y-m-d H:i'),
        ];
    }

    public static function describe(array $f): string
    {
        $parts = [];
        if (! empty($f['from']) || ! empty($f['to'])) {
            $parts[] = 'Period: '.($f['from'] ?? '…').' to '.($f['to'] ?? '…');
        }
        if (! empty($f['status'])) {
            $parts[] = 'Status: '.LeaveStatus::from($f['status'])->label();
        }
        if (! empty($f['department'])) {
            $parts[] = 'Department: '.Department::find($f['department'])?->name;
        }
        if (! empty($f['category'])) {
            $parts[] = 'Category: '.LeaveCategory::find($f['category'])?->name;
        }
        if (! empty($f['employee'])) {
            $parts[] = 'Employee: '.User::find($f['employee'])?->name;
        }
        if (! empty($f['search'])) {
            $parts[] = 'Search: "'.$f['search'].'"';
        }

        return $parts ? implode(' · ', $parts) : 'All submitted applications';
    }
}
