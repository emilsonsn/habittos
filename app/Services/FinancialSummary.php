<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;

class FinancialSummary
{
    public function forMonth(Workspace $workspace, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $entries = $workspace->entries()->with('category')->where('date', '>=', $start->toDateString())->where('date', '<', $start->addMonth()->toDateString())->orderByDesc('date')->orderByDesc('id')->get();
        $income = $entries->where('type', 'income')->sum('amount_cents');
        $expenses = $entries->where('type', 'expense')->sum('amount_cents');
        $invested = $entries->where('type', 'investment')->sum('amount_cents');
        $settled = $entries->where('paid', true);
        $balance = $settled->where('type', 'income')->sum('amount_cents') - $settled->whereIn('type', ['expense', 'investment'])->sum('amount_cents');
        $budget = $workspace->budgets()->where('month', $month)->first();
        $reserve = $budget?->reserve_cents ?? 0;
        $weeklyLimit = $budget?->weekly_limit_cents ?? 0;
        $weeks = collect(range(1, (int) ceil($start->daysInMonth / 7)))->map(function ($week) use ($entries, $weeklyLimit) {
            $spent = $entries->where('type', 'expense')->where('is_fixed', false)->filter(fn ($entry) => (int) ceil($entry->date->day / 7) === $week)->sum('amount_cents');

            return ['number' => $week, 'spent' => $spent, 'limit' => $weeklyLimit];
        });
        $byCategory = $entries->where('type', 'expense')->groupBy(fn ($entry) => $entry->category?->name ?? 'Sem categoria')->map(fn ($items) => $items->sum('amount_cents'))->sortDesc();

        return compact('entries', 'income', 'expenses', 'invested', 'balance', 'reserve', 'weeklyLimit', 'weeks', 'byCategory') + [
            'investmentBalance' => $budget?->investment_balance_cents,
            'fixedIncome' => $entries->where('type', 'income')->where('is_fixed', true)->sum('amount_cents'),
            'projected' => $income - $expenses - $invested - $reserve,
            'fixed' => $entries->where('type', 'expense')->where('is_fixed', true)->sum('amount_cents'),
            'pending' => $entries->where('paid', false)->whereIn('type', ['expense', 'investment'])->sum('amount_cents'),
        ];
    }
}
