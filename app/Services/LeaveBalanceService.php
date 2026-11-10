<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leave balances per employee and category.
 *
 * Cycles run from the employee's employment start date (BCEA s20 and s22), or from
 * 1 January of the year their account was created if no start date is recorded.
 * Days are counted against the cycle in which the leave starts.
 */
class LeaveBalanceService
{
    /** Statuses that reserve days until a decision is made. */
    public const PENDING = [
        LeaveStatus::Submitted, LeaveStatus::UnderManagerReview,
        LeaveStatus::PendingDirectorApproval, LeaveStatus::ReturnedForInformation,
    ];

    /**
     * Balances for one user: every tracked category, plus untracked categories with leave this year.
     *
     * @return Collection<int, LeaveBalance> keyed by category id
     */
    public function forUser(User $user, ?int $excludeApplicationId = null): Collection
    {
        return $this->forUsers(collect([$user]), $excludeApplicationId)->get($user->id, collect());
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, Collection<int, LeaveBalance>> keyed by user id, then category id
     */
    public function forUsers(Collection $users, ?int $excludeApplicationId = null): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $today = CarbonImmutable::today();
        $categories = LeaveCategory::orderBy('sort_order')->orderBy('name')->get();

        // Work out every cycle first, so one query can fetch all the leave we need.
        $cycles = [];
        foreach ($users as $user) {
            foreach ($categories as $cat) {
                $cycles[$user->id][$cat->id] = $cat->hasEntitlementFor($user)
                    ? $this->cycle($user, $cat->entitlement_cycle_months, $today)
                    : [$today->startOfYear(), $today->endOfYear()->startOfDay()];
            }
        }
        $earliest = collect($cycles)->flatten(1)->map(fn ($c) => $c[0])->min();

        $apps = LeaveApplication::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereIn('status', array_map(fn ($s) => $s->value, [LeaveStatus::Approved, ...self::PENDING]))
            ->whereDate('start_date', '>=', $earliest)
            ->when($excludeApplicationId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get(['id', 'user_id', 'leave_category_id', 'status', 'start_date', 'working_days'])
            ->groupBy('user_id');

        return $users->mapWithKeys(function (User $user) use ($categories, $cycles, $apps, $today) {
            $mine = $apps->get($user->id, collect())->groupBy('leave_category_id');

            $balances = $categories->mapWithKeys(function (LeaveCategory $cat) use ($user, $cycles, $mine, $today) {
                [$start, $end] = $cycles[$user->id][$cat->id];
                $inCycle = $mine->get($cat->id, collect())
                    ->filter(fn ($a) => $a->start_date->betweenIncluded($start, $end));
                $approved = $inCycle->filter(fn ($a) => $a->status === LeaveStatus::Approved);

                return [$cat->id => new LeaveBalance(
                    category: $cat,
                    entitlement: $cat->hasEntitlementFor($user) ? (float) $cat->entitlement_days : null,
                    cycleStart: $start,
                    cycleEnd: $end,
                    taken: (float) $approved->sum('working_days'),
                    scheduled: (float) $approved->filter(fn ($a) => $a->start_date->isAfter($today))->sum('working_days'),
                    pending: (float) $inCycle->filter(fn ($a) => in_array($a->status, self::PENDING, true))->sum('working_days'),
                )];
            });

            // Untracked categories are only listed when there is something to show.
            return [$user->id => $balances->filter(fn (LeaveBalance $b) => $b->tracked() || $b->taken > 0 || $b->pending > 0)];
        });
    }

    /**
     * The cycle that contains $on, as [first day, last day].
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cycle(User $user, int $months, CarbonImmutable $on): array
    {
        $anchor = $user->employment_start_date
            ? CarbonImmutable::parse($user->employment_start_date)->startOfDay()
            : CarbonImmutable::parse($user->created_at ?? $on)->startOfYear();
        $months = max(1, $months);

        // Before employment starts, the first cycle is the one shown.
        $n = $on->lessThan($anchor) ? 0 : intdiv((int) $anchor->diffInMonths($on), $months);
        $start = $anchor->addMonthsNoOverflow($n * $months);
        if ($start->greaterThan($on) && $n > 0) {
            $start = $anchor->addMonthsNoOverflow(--$n * $months);
        }

        return [$start, $anchor->addMonthsNoOverflow(($n + 1) * $months)->subDay()];
    }
}
