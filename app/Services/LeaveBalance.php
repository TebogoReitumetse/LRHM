<?php

namespace App\Services;

use App\Models\LeaveCategory;
use Carbon\CarbonImmutable;

/** One employee's position for one leave category in the current cycle. */
final class LeaveBalance
{
    public function __construct(
        public readonly LeaveCategory $category,
        public readonly ?float $entitlement,   // null = no balance tracked for this category/user
        public readonly CarbonImmutable $cycleStart,
        public readonly CarbonImmutable $cycleEnd,
        public readonly float $taken,          // approved, including approved leave still to come
        public readonly float $scheduled,      // part of $taken that has not started yet
        public readonly float $pending,        // submitted and awaiting a decision
    ) {}

    public function tracked(): bool
    {
        return $this->entitlement !== null;
    }

    public function available(): ?float
    {
        return $this->tracked() ? $this->entitlement - $this->taken : null;
    }

    /** Available once everything pending is approved. */
    public function availableAfterPending(): ?float
    {
        return $this->tracked() ? $this->entitlement - $this->taken - $this->pending : null;
    }

    public function isOverdrawn(): bool
    {
        return $this->tracked() && $this->available() < 0;
    }

    /** 0–100, share of the entitlement that is used (taken) and reserved (pending). */
    public function percentTaken(): float
    {
        return $this->tracked() && $this->entitlement > 0 ? min(100, $this->taken / $this->entitlement * 100) : 0;
    }

    public function percentPending(): float
    {
        return $this->tracked() && $this->entitlement > 0
            ? min(100 - $this->percentTaken(), $this->pending / $this->entitlement * 100) : 0;
    }

    public function cycleLabel(): string
    {
        return $this->cycleStart->format('d M Y').' – '.$this->cycleEnd->format('d M Y');
    }

    /** Format a day count without a trailing ".0". */
    public static function days(?float $n): string
    {
        if ($n === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
