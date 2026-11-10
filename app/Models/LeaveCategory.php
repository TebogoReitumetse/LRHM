<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeaveCategory extends Model
{
    protected $fillable = ['name', 'description', 'requires_document', 'requires_reason', 'is_active', 'sort_order',
        'entitlement_days', 'entitlement_cycle_months', 'entitlement_for_contractors'];

    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean', 'requires_reason' => 'boolean', 'is_active' => 'boolean',
            'entitlement_days' => 'float', 'entitlement_cycle_months' => 'integer', 'entitlement_for_contractors' => 'boolean',
        ];
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
