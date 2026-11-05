<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contract extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const TYPES = [
        'fixed_term' => 'Fixed-term employment',
        'contractor' => 'Independent contractor',
        'internship' => 'Internship / learnership',
        'part_time' => 'Part-time fixed-term',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'date_signed' => 'date',
            'commencement_date' => 'date',
            'renewal_decision_date' => 'date',
            'ended_on' => 'date',
            'dates_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault(['name' => '—']);
    }

    public function renewalRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewal_recorded_by');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'previous_contract_id');
    }

    public function next(): HasOne
    {
        return $this->hasOne(Contract::class, 'previous_contract_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class)->latest()->orderByDesc('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ContractReminder::class)->orderByDesc('scheduled_for')->orderByDesc('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->contract_type] ?? ucfirst(str_replace('_', ' ', $this->contract_type));
    }

    public function durationLabel(): string
    {
        return self::formatDuration($this->start_date, $this->end_date);
    }

    /** Duration from start to end date, counting the end date as a full day. */
    public static function formatDuration(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($end->lt($start)) {
            return '—';
        }
        $diff = $start->copy()->startOfDay()->diff($end->copy()->addDay()->startOfDay());
        $months = $diff->y * 12 + $diff->m;
        $parts = [];
        if ($months) {
            $parts[] = $months.' '.str('month')->plural($months);
        }
        if ($diff->d || ! $months) {
            $parts[] = $diff->d.' '.str('day')->plural($diff->d);
        }

        return implode(', ', $parts);
    }

    /** Calendar days until the end date (negative once expired). */
    public function daysRemaining(): int
    {
        return (int) today()->diffInDays($this->end_date, false);
    }

    public function remainingLabel(): string
    {
        if (! in_array($this->status, ContractStatus::monitored(), true)) {
            return '—';
        }
        $days = $this->daysRemaining();
        if ($days < 0) {
            return 'Expired '.abs($days).' '.str('day')->plural(abs($days)).' ago';
        }
        if ($days === 0) {
            return 'Expires today';
        }

        $label = $days.' '.str('day')->plural($days);

        return $days > 31 ? $label.' ('.self::formatDuration(today()->addDay(), $this->end_date).')' : $label;
    }

    public function urgencyClass(): string
    {
        if (! in_array($this->status, ContractStatus::monitored(), true)) {
            return 'text-gray-500';
        }
        $d = $this->daysRemaining();

        return match (true) {
            $d < 0 => 'text-red-600 font-semibold',
            $d <= 30 => 'text-red-600',
            $d <= 60 => 'text-brand-700',
            $d <= 90 => 'text-amber-600',
            default => 'text-accent-700',
        };
    }

    public function currentDocument(): ?ContractDocument
    {
        return $this->documents->where('document_type', 'signed_contract')->firstWhere('is_current', true);
    }

    public function scopeMonitored(Builder $q): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, ContractStatus::monitored()));
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->whereIn('status', [ContractStatus::Active->value, ContractStatus::DueToExpire->value])
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays($days));
    }

    public function scopeAwaitingDecision(Builder $q): Builder
    {
        return $q->monitored()->whereNull('renewal_decision')
            ->whereDate('end_date', '<=', today()->addDays(max(\App\Models\Setting::reminderDays() ?: [90])));
    }

    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', "%$s%")
                ->orWhere('employee_number', 'like', "%$s%")
                ->orWhere('reference', 'like', "%$s%")))
            ->when($f['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('contract_type', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['expiry'] ?? null, function ($q, $v) {
                match ($v) {
                    '30', '60', '90' => $q->expiringWithin((int) $v),
                    'this_month' => $q->monitored()->whereBetween('end_date', [today()->startOfMonth(), today()->endOfMonth()]),
                    'expired' => $q->where('status', ContractStatus::Expired->value),
                    'awaiting_decision' => $q->awaitingDecision(),
                    default => null,
                };
            })
            ->when($f['expiry_from'] ?? null, fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
            ->when($f['expiry_to'] ?? null, fn ($q, $v) => $q->whereDate('end_date', '<=', $v));
    }

    protected function auditDescription(string $event): string
    {
        return 'Contract '.($this->reference ?? '#'.$this->id).' ('.$this->full_name.') '.$event;
    }
}
