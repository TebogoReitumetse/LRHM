<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'employee_number', 'id_number', 'signature', 'department_id', 'job_title', 'email', 'phone',
        'role', 'employment_type', 'employment_start_date', 'can_view_contract', 'is_active', 'password', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token', 'signature'];

    protected array $auditExclude = ['last_login_at', 'signature'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'can_view_contract' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'employment_start_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function absenceRecords(): HasMany
    {
        return $this->hasMany(AbsenceRecord::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeWithRole(Builder $q, Role ...$roles): Builder
    {
        return $q->whereIn('role', array_map(fn ($r) => $r->value, $roles));
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isEmployee(): bool
    {
        return $this->role === Role::Employee;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function isDirector(): bool
    {
        return $this->role === Role::Director;
    }

    /** Keeps the user's latest signature on their profile so the next form is pre-signed. */
    public function rememberSignature(?string $signature): void
    {
        if ($signature && $this->signature !== $signature) {
            $this->update(['signature' => $signature]);
        }
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Manager or Director: may see leave registers, dashboards and reports. */
    public function isManagement(): bool
    {
        return $this->hasRole(Role::Manager, Role::Director);
    }

    public function isContractor(): bool
    {
        return $this->employment_type === 'contractor';
    }

    /** "Department / Position" as on the paper leave form. */
    public function departmentPosition(): string
    {
        return collect([$this->department?->name, $this->job_title])->filter()->implode(' / ') ?: '—';
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
