<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('department')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('employee_number', 'like', "%$s%")->orWhere('username', 'like', "%$s%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(),
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true, 'can_view_contract' => true, 'role' => Role::Employee, 'employment_type' => 'employee']),
            'departments' => Department::orderBy('name')->get(),
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $temp = $data['password'] ?? $this->temporaryPassword();
        $user = User::create($data + ['password' => $temp, 'password_changed_at' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', "Account created for {$user->name}.")
            ->with('credentials', ['username' => $user->username, 'password' => $temp]);
    }

    public function edit(Request $request, User $user)
    {
        $this->guardRole($request->user(), $user);

        return view('admin.users.form', [
            'user' => $user,
            'departments' => Department::orderBy('name')->get(),
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardRole($request->user(), $user);
        $data = $this->validated($request, $user);
        unset($data['password']);

        if ($user->is($request->user())) {
            // Users may not deactivate themselves or change their own role.
            $data['is_active'] = true;
            $data['role'] = $user->role->value;
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} updated.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->guardRole($request->user(), $user);
        $temp = $this->temporaryPassword();
        $user->update(['password' => $temp, 'password_changed_at' => null]);
        AuditLog::record('auth.password_reset', $user, "Password reset for {$user->name}");

        return back()->with('success', "Temporary password issued for {$user->name}.")
            ->with('credentials', ['username' => $user->username, 'password' => $temp]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $roles = array_map(fn ($r) => $r->value, $this->assignableRoles($request->user()));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')->ignore($user)],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'role' => ['required', Rule::in($roles)],
            'employment_type' => ['required', 'in:employee,contractor'],
            'can_view_contract' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', Password::defaults()],
        ]);
        $data['can_view_contract'] = $request->boolean('can_view_contract');
        $data['is_active'] = $request->boolean('is_active');

        return array_filter($data, fn ($v) => $v !== null) + ['email' => null, 'phone' => null, 'department_id' => null, 'job_title' => null];
    }

    /** Managers handle staff accounts; only the administrator can create Director / Admin accounts. */
    private function assignableRoles(User $actor): array
    {
        return $actor->isAdmin() ? Role::cases() : [Role::Employee, Role::Manager];
    }

    private function guardRole(User $actor, User $target): void
    {
        abort_if(! $actor->isAdmin() && $target->hasRole(Role::Director, Role::Admin), 403, 'Only the system administrator can manage this account.');
    }

    private function temporaryPassword(): string
    {
        return 'Lv!'.Str::password(10, symbols: false).'9a';
    }
}
