<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OpenFinancialMonth
{
    /**
     * Inicializa o mês uma única vez, com cópias independentes dos lançamentos fixos anteriores.
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
            $calendar = app(FinancialCalendar::class);
            $period = $calendar->period($workspace, $month);
            $previous = $calendar->period($workspace, $start->subMonth()->format('Y-m'));
            $fixedEntries = $workspace->entries()
                ->whereIn('type', ['income', 'expense'])
                ->where('is_fixed', true)
                ->where('date', '>=', $previous['start']->toDateString())
                ->where('date', '<', $previous['end']->toDateString())
                ->get();

            foreach ($fixedEntries as $entry) {
                $dueDate = CarbonImmutable::instance($entry->date)->addMonthNoOverflow()
                    ->max($period['start'])->min($period['end']->subDay());
                $workspace->entries()->create([
                    'category_id' => $entry->category_id,
                    'description' => $entry->description,
                    'type' => $entry->type,
                    'amount_cents' => $entry->amount_cents,
                    'date' => $dueDate->toDateString(),
                    'paid' => false,
                    'is_fixed' => true,
                    'notes' => $entry->notes,
                ]);
            }

            return $fixedEntries->count();
        }, attempts: 5);
    }
}
