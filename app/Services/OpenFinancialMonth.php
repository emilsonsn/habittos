<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OpenFinancialMonth
{
    /**
     * Inicializa o mês uma única vez, com cópias independentes dos fixos anteriores.
     * O marcador único também impede cópias simultâneas por usuários diferentes.
     */
    public function handle(Workspace $workspace, string $month): int
    {
        return DB::transaction(function () use ($workspace, $month) {
            $inserted = DB::table('financial_months')->insertOrIgnore([
                'workspace_id' => $workspace->id,
                'month' => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! $inserted) {
                return 0;
            }

            $start = CarbonImmutable::createFromFormat('!Y-m', $month);
            $previous = $start->subMonth();
            $fixedExpenses = $workspace->entries()
                ->where('type', 'expense')
                ->where('is_fixed', true)
                ->where('date', '>=', $previous->toDateString())
                ->where('date', '<', $start->toDateString())
                ->get();

            foreach ($fixedExpenses as $expense) {
                $workspace->entries()->create([
                    'category_id' => $expense->category_id,
                    'description' => $expense->description,
                    'type' => 'expense',
                    'amount_cents' => $expense->amount_cents,
                    'date' => $start->day(min($expense->date->day, $start->daysInMonth))->toDateString(),
                    'paid' => false,
                    'is_fixed' => true,
                    'notes' => $expense->notes,
                ]);
            }

            return $fixedExpenses->count();
        }, attempts: 5);
    }
}
