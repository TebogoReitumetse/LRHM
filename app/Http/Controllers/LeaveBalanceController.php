<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\LeaveCategory;
use App\Models\User;
use App\Services\LeaveBalance;
use App\Services\LeaveBalanceService;
use App\Support\Export;
use Illuminate\Http\Request;

/** Leave balances for all staff (management and administrators). */
class LeaveBalanceController extends Controller
{
    public function __construct(private LeaveBalanceService $balances) {}

    public function index(Request $request)
    {
        [$users, $balances, $tracked] = $this->data($request);

        if ($request->query('low')) {
            $annual = $tracked->first();
            $users = $users->filter(fn ($u) => $annual && ($b = $balances[$u->id][$annual->id] ?? null)
                && $b->tracked() && $b->availableAfterPending() <= 3)->values();
        }

        return view('balances.index', [
            'users' => $users,
            'balances' => $balances,
            'tracked' => $tracked,
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only('search', 'department', 'type', 'low'),
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);

        $balances = $this->balances->forUser($user);
        // Individual applications are confidential leave records: management only.
        $applications = $request->user()->isManagement()
            ? $user->leaveApplications()->with('category')
                ->whereIn('status', array_map(fn ($s) => $s->value, [LeaveStatus::Approved, ...LeaveBalanceService::PENDING]))
                ->whereDate('start_date', '>=', $balances->min(fn ($b) => $b->cycleStart) ?? today()->startOfYear())
                ->latest('start_date')->get()
            : null;

        return view('balances.show', ['employee' => $user->load('department'), 'balances' => $balances, 'applications' => $applications]);
    }

    public function export(Request $request)
    {
        [$users, $balances, $tracked] = $this->data($request);

        $headings = ['Employee', 'Employee no.', 'Department', 'Employment type', 'Employment start date'];
        foreach ($tracked as $c) {
            array_push($headings, "{$c->name} entitlement", "{$c->name} taken", "{$c->name} pending", "{$c->name} available", "{$c->name} cycle");
        }
        $headings[] = 'Other leave taken this year';

        $rows = $users->map(function (User $u) use ($balances, $tracked) {
            $mine = $balances[$u->id] ?? collect();
            $row = [$u->name, $u->employee_number, $u->department?->name, ucfirst($u->employment_type), $u->employment_start_date?->format('Y-m-d') ?? ''];
            foreach ($tracked as $c) {
                $b = $mine[$c->id] ?? null;
                array_push($row, ...($b && $b->tracked()
                    ? [LeaveBalance::days($b->entitlement), LeaveBalance::days($b->taken), LeaveBalance::days($b->pending), LeaveBalance::days($b->available()), $b->cycleLabel()]
                    : ['n/a', $b ? LeaveBalance::days($b->taken) : '0', $b ? LeaveBalance::days($b->pending) : '0', 'n/a', '']));
            }
            $row[] = LeaveBalance::days($mine->reject(fn ($b) => $b->tracked() || $tracked->contains('id', $b->category->id))->sum('taken'));

            return $row;
        });

        return Export::csv('leave-balances-'.now()->format('Ymd-His'), $headings, $rows);
    }

    /** Filtered staff, their balances, and the categories that have an entitlement. */
    private function data(Request $request): array
    {
        $users = User::with('department')->active()->where('role', '!=', Role::Admin->value)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->type, fn ($q, $t) => $q->where('employment_type', $t))
            ->orderBy('name')->get();

        $tracked = LeaveCategory::whereNotNull('entitlement_days')->orderBy('sort_order')->orderBy('name')->get();

        return [$users, $this->balances->forUsers($users), $tracked];
    }
}
