<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;

class FinancialCalendar
{
    public function startsOn(Workspace $workspace, string $month): CarbonImmutable
    {
        $date = $workspace->budgets()->where('month', $month)->value('starts_on');

        return $date
            ? CarbonImmutable::parse($date)->startOfDay()
            : CarbonImmutable::createFromFormat('!Y-m', $month);
    }

    /** The end is exclusive, so consecutive cycles never overlap or leave gaps. */
    public function period(Workspace $workspace, string $month): array
    {
        $nextMonth = CarbonImmutable::createFromFormat('!Y-m', $month)->addMonth()->format('Y-m');

        return [
            'start' => $this->startsOn($workspace, $month),
            'end' => $this->startsOn($workspace, $nextMonth),
        ];
    }

    public function monthForDate(Workspace $workspace, string $date): string
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $month = $day->format('Y-m');

        return $day->isBefore($this->startsOn($workspace, $month))
            ? $day->startOfMonth()->subMonth()->format('Y-m')
            : $month;
    }
}
