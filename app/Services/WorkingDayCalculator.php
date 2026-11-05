<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Working calendar: Monday to Friday, excluding public holidays
 * captured under Settings > Public holidays.
 */
class WorkingDayCalculator
{
    /** @var array<string,string>|null */
    private ?array $holidays = null;

    public function holidays(): array
    {
        return $this->holidays ??= PublicHoliday::pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [Carbon::parse($date)->toDateString() => $name])
            ->all();
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! isset($this->holidays()[$date->toDateString()]);
    }

    public function count(CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }
        $days = 0;
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $day) {
            if ($this->isWorkingDay($day)) {
                $days++;
            }
        }

        return $days;
    }

    /** Public holidays that fall inside the period. */
    public function holidaysBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        return collect($this->holidays())
            ->filter(fn ($name, $date) => $date >= $start->toDateString() && $date <= $end->toDateString())
            ->all();
    }

    /** First working day after the given date: the default expected return date. */
    public function nextWorkingDay(CarbonInterface $date): Carbon
    {
        $d = Carbon::parse($date)->addDay();
        while (! $this->isWorkingDay($d)) {
            $d->addDay();
        }

        return $d;
    }
}
