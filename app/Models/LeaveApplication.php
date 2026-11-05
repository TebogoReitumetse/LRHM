<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApplication extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'return_date' => 'date',
            'working_days' => 'decimal:1',
            'declaration_accepted' => 'boolean',
            'declaration_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'manager_reviewed_at' => 'datetime',
            'returned_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LeaveCategory::class, 'leave_category_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeaveDocument::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LeaveApplicationAction::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeStatus(Builder $q, LeaveStatus ...$statuses): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
    }

    /** Excludes private drafts. Used for all management views. */
    public function scopeSubmittedOnly(Builder $q): Builder
    {
        return $q->where('status', '!=', LeaveStatus::Draft->value);
    }

    /** Applications that overlap a date range. */
    public function scopeOverlapping(Builder $q, $from, $to): Builder
    {
        return $q->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from);
    }

    /** Apply the standard register filters. */
    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('reference', 'like', "%$s%")
                ->orWhere('employee_name', 'like', "%$s%")
                ->orWhere('employee_number', 'like', "%$s%")))
            ->when($f['employee'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['department'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('department_id', $v)))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('leave_category_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(($f['from'] ?? null) || ($f['to'] ?? null),
                fn ($q) => $q->overlapping(($f['from'] ?? null) ?: '1900-01-01', ($f['to'] ?? null) ?: '2999-12-31'));
    }

    public function logAction(string $action, ?LeaveStatus $from, ?LeaveStatus $to, ?string $comment = null): void
    {
        $this->actions()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'comment' => $comment,
        ]);

        AuditLog::record(
            'leave.'.$action,
            $this,
            "Leave {$this->reference}: {$action}",
            $from ? ['status' => $from->value] : null,
            $to ? array_filter(['status' => $to->value, 'comment' => $comment]) : null,
        );
    }

    protected function auditDescription(string $event): string
    {
        return 'Leave application '.($this->reference ?? '#'.$this->id).' '.$event;
    }
}
