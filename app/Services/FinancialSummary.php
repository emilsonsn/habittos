<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FinancialSummary
{
    public function forMonth(Workspace $workspace, string $month): array
    {
        $period = app(FinancialCalendar::class)->period($workspace, $month);
        $start = $period['start'];
        $end = $period['end'];
        // Short transition cycles still have four non-overlapping ranges.
        $weekLength = min(7, max(1, intdiv((int) $start->diffInDays($end), 3)));
        $entries = $workspace->entries()->with('category')->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->orderByDesc('date')->orderByDesc('id')->get();
        $income = $entries->where('type', 'income')->sum('amount_cents');
        $expenses = $entries->where('type', 'expense')->sum('amount_cents');
        $fixed = $entries->where('type', 'expense')->where('is_fixed', true)->sum('amount_cents');
        $invested = $entries->where('type', 'investment')->sum('amount_cents');
        $settled = $entries->where('paid', true);
        $balance = $settled->where('type', 'income')->sum('amount_cents') - $settled->whereIn('type', ['expense', 'investment'])->sum('amount_cents');
        $budget = $workspace->budgets()->where('month', $month)->first();
        $reserve = $budget?->reserve_cents ?? 0;
        $monthlyLimit = max(0, $income - $fixed - $invested - $reserve);
        $weeklyLimit = intdiv($monthlyLimit, 4);
        $weeks = collect(range(1, 4))->map(function ($week) use ($entries, $weeklyLimit, $start, $end, $weekLength) {
            $weekStart = $start->addDays(($week - 1) * $weekLength);
            $weekEnd = $week === 4 ? $end : $weekStart->addDays($weekLength)->min($end);
            $spent = $entries->where('type', 'expense')->where('is_fixed', false)->filter(fn ($entry) => $entry->date->gte($weekStart) && $entry->date->lt($weekEnd))->sum('amount_cents');

            return ['number' => $week, 'spent' => $spent, 'limit' => $weeklyLimit, 'startDay' => $weekStart->day, 'endDay' => $weekEnd->subDay()->day, 'dateLabel' => $weekStart->lt($weekEnd) ? $weekStart->format('d/m').'–'.$weekEnd->subDay()->format('d/m') : 'Sem dias neste ciclo'];
        });
        $weeklyPlan = $this->weeklyPlan($start, $end, $weekLength, $weeklyLimit, $weeks);
        $byCategory = $entries->where('type', 'expense')->groupBy(fn ($entry) => $entry->category?->name ?? 'Sem categoria')->map(fn ($items) => $items->sum('amount_cents'))->sortDesc();

        return compact('entries', 'income', 'expenses', 'invested', 'balance', 'reserve', 'weeklyLimit', 'weeks', 'weeklyPlan', 'monthlyLimit', 'byCategory', 'period') + [
            'available' => $balance - $reserve,
            'investmentBalance' => $budget?->investment_balance_cents,
            'fixedIncome' => $entries->where('type', 'income')->where('is_fixed', true)->sum('amount_cents'),
            'projected' => $income - $expenses - $invested - $reserve,
            'fixed' => $fixed,
            'pending' => $entries->where('paid', false)->whereIn('type', ['expense', 'investment'])->sum('amount_cents'),
        ];
    }

    private function weeklyPlan(CarbonImmutable $start, CarbonImmutable $end, int $weekLength, int $weeklyLimit, Collection $weeks): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $completedWeeks = match (true) {
            $today->gte($end) => 4,
            $today->lt($start) => 0,
            default => min(3, intdiv((int) $start->diffInDays($today), $weekLength)),
        };
        $remainingWeeks = 4 - $completedWeeks;
        $pastExcess = $weeks->take($completedWeeks)->sum(fn ($week) => max(0, $week['spent'] - $weeklyLimit));
        $remainingBudget = max(0, $weeklyLimit * $remainingWeeks - $pastExcess);
        $adjustedLimit = $remainingWeeks > 0 ? intdiv($remainingBudget, $remainingWeeks) : null;
        $currentWeekRemaining = $today->gte($start) && $today->lt($end)
            ? $adjustedLimit - $weeks->get($completedWeeks)['spent']
            : null;

        return [
            'remainingWeeks' => $remainingWeeks,
            // Only completed weeks above the base limit reduce the remaining allowance.
            // Current/future spending and savings in earlier weeks do not affect it.
            'adjustedLimit' => $adjustedLimit,
            'currentWeekRemaining' => $currentWeekRemaining,
        ];
    }
}
