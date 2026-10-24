<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenFinancialMonth
{
    /**
     * Copia os fixos anteriores somente para um ciclo sem lançamentos.
     * O bloqueio do ambiente serializa a sincronização e os salvamentos de lançamentos.
     */
    public function handle(Workspace $workspace, string $month, string $cycleStart, string $cycleEnd): int
    {
        app(FinancialCalendar::class)->validatePeriod($month, $cycleStart, $cycleEnd);

        return DB::transaction(function () use ($workspace, $month, $cycleStart, $cycleEnd) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::createFromFormat('!Y-m', $month);
            $calendar = app(FinancialCalendar::class);
            $period = $calendar->period($workspace, $month);
            $this->assertEmpty($workspace, $period['start'], $period['end']);
            // Revalida também o período proposto para impedir contornar a restrição mudando datas.
            $this->assertEmpty($workspace, CarbonImmutable::parse($cycleStart), CarbonImmutable::parse($cycleEnd)->addDay());
            $previous = $calendar->period($workspace, $start->subMonth()->format('Y-m'));
            $fixedEntries = $workspace->entries()
                ->whereIn('type', ['income', 'expense'])
                ->where('is_fixed', true)
                ->where('date', '>=', $previous['start']->toDateString())
                ->where('date', '<', $previous['end']->toDateString())
                ->get();

            $calendar->savePeriod($workspace, $month, $cycleStart, $cycleEnd);
            if ($fixedEntries->isEmpty()) {
                return 0;
            }

            DB::table('financial_months')->insertOrIgnore([
                'workspace_id' => $workspace->id,
                'month' => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($fixedEntries as $entry) {
                $workspace->entries()->create([
                    'category_id' => $entry->category_id,
                    'description' => $entry->description,
                    'type' => $entry->type,
                    'amount_cents' => $entry->amount_cents,
                    'date' => $cycleStart,
                    'paid' => true,
                    'is_fixed' => true,
                    'notes' => $entry->notes,
                ]);
            }

            return $fixedEntries->count();
        }, attempts: 5);
    }

    private function assertEmpty(Workspace $workspace, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($workspace->entries()->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->exists()) {
            throw ValidationException::withMessages(['synchronization' => 'Este mês já possui lançamentos e não pode ser sincronizado.']);
        }
    }
}
