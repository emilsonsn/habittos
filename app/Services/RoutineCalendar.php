<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RoutineCalendar
{
    public function currentWeek(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfWeek(CarbonImmutable::MONDAY);
    }

    public function start(int $year, int $week): CarbonImmutable
    {
        validator(compact('year', 'week'), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'week' => ['required', 'integer', 'between:1,53'],
        ])->validate();
        $start = CarbonImmutable::create($year, 1, 4, 0, 0, 0, config('app.timezone'))->setISODate($year, $week, 1);
        if ($start->isoWeekYear() !== $year || $start->isoWeek() !== $week) {
            throw ValidationException::withMessages(['week' => 'Esta semana não existe no ano informado.']);
        }

        return $start;
    }
}
