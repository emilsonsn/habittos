<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    public function validatePeriod(string $month, string $start, string $end): void
    {
        validator(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();
        $first = CarbonImmutable::createFromFormat('!Y-m', $month);
        validator(['cycleStartInput' => $start, 'cycleEndInput' => $end], [
            'cycleStartInput' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$first->toDateString(), 'before_or_equal:'.$first->endOfMonth()->toDateString()],
            'cycleEndInput' => ['required', 'date_format:Y-m-d', 'after_or_equal:cycleStartInput', 'after_or_equal:'.$first->endOfMonth()->toDateString(), 'before:'.$first->addMonth()->endOfMonth()->toDateString()],
        ], attributes: ['cycleStartInput' => 'início do ciclo', 'cycleEndInput' => 'fim do ciclo'])->validate();
    }

    /** O fim informado é inclusivo; o próximo ciclo começa no dia seguinte. */
    public function savePeriod(Workspace $workspace, string $month, string $start, string $end): void
    {
        $this->validatePeriod($month, $start, $end);
        DB::transaction(function () use ($workspace, $month, $start, $end) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $nextMonth = CarbonImmutable::createFromFormat('!Y-m', $month)->addMonth()->format('Y-m');
            $workspace->budgets()->updateOrCreate(['month' => $month], ['starts_on' => $start]);
            $workspace->budgets()->updateOrCreate(['month' => $nextMonth], ['starts_on' => CarbonImmutable::parse($end)->addDay()->toDateString()]);
        }, attempts: 5);
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
