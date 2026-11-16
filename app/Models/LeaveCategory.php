<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeaveCategory extends Model
{
    /** Days beyond a tracked balance are counted against this category. */
    public const UNPAID = 'Unpaid Leave';

    protected $fillable = ['name', 'description', 'requires_document', 'document_after_days', 'requires_reason', 'is_active', 'sort_order',
        'entitlement_days', 'entitlement_cycle_months', 'entitlement_for_contractors'];

    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean', 'requires_reason' => 'boolean', 'is_active' => 'boolean',
            'document_after_days' => 'integer', 'entitlement_days' => 'float', 'entitlement_cycle_months' => 'integer', 'entitlement_for_contractors' => 'boolean',
        ];
    }

    /** Whether a supporting document is needed for an application of this many working days. */
    public function requiresDocumentFor(float $days): bool
    {
        return $this->requires_document && ($this->document_after_days === null || $days > $this->document_after_days);
    }

    /**
     * Short notes shown next to a leave type on the leave form, keyed by category name.
     *
     * @var array<string, string>
     */
    public const FORM_NOTES = [
        'Maternity / Paternity Leave' => 'Maternity: up to 4 consecutive months. Paternity: 10 consecutive days',
        self::UNPAID => 'Without pay, subject to approval',
        'Other' => 'e.g. study or religious leave, subject to approval',
    ];

    /**
     * Entitlements for leave types without a tracked day balance, shown in the balance box on the leave form.
     *
     * @var array<string, array<string, string>>
     */
    public const UNTRACKED_ENTITLEMENTS = [
        'Maternity / Paternity Leave' => ['Maternity' => '4 consecutive months', 'Paternity' => '10 consecutive days'],
    ];

    /** The note shown in brackets next to this leave type on the leave form, if any. */
    public function formNote(): ?string
    {
        if ($this->requires_document) {
            return $this->document_after_days
                ? "Attach medical certificate if more than {$this->document_after_days} days"
                : 'Attach supporting document';
        }

        return self::FORM_NOTES[$this->name] ?? null;
    }

    /** "Reason required" is used as "employee must specify the type of leave" (the Other option). */
    public function requiresSpecification(): bool
    {
        return $this->requires_reason;
    }

    /** Whether a balance is tracked for this category for the given user. */
    public function hasEntitlementFor(User $user): bool
    {
        return $this->entitlement_days !== null && (! $user->isContractor() || $this->entitlement_for_contractors);
    }

    /** e.g. "per year", "per 3-year cycle", "per 6 months". */
    public function cycleLabel(): string
    {
        $m = $this->entitlement_cycle_months;

        return match (true) {
            $m === 12 => 'per year',
            $m % 12 === 0 => 'per '.($m / 12).'-year cycle',
            default => "per {$m} months",
        };
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
