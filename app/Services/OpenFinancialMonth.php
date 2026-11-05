<?php

namespace App\Services;

use App\Enums\EntryType;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenFinancialMonth
{
    /**
     * Copia fixos e despesas parceladas anteriores somente para um ciclo sem lançamentos.
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
            $recurringEntries = $workspace->entries()
                ->whereIn('type', [EntryType::Income->value, EntryType::Expense->value])
                ->where(function ($query) {
                    $query->where('is_fixed', true)
                        ->orWhere(fn ($query) => $query->where('type', EntryType::Expense->value)->where('is_installment', true));
                })
                ->where('date', '>=', $previous['start']->toDateString())
                ->where('date', '<', $previous['end']->toDateString())
                ->get();

            $calendar->savePeriod($workspace, $month, $cycleStart, $cycleEnd);
            if ($recurringEntries->isEmpty()) {
                return 0;
            }

            DB::table('financial_months')->insertOrIgnore([
                'workspace_id' => $workspace->id,
                'month' => $month,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($recurringEntries as $entry) {
                $workspace->entries()->create([
                    'category_id' => $entry->category_id,
                    'description' => $entry->description,
                    'type' => $entry->type,
                    'amount_cents' => $entry->amount_cents,
                    'date' => $cycleStart,
                    'paid' => false,
                    'is_fixed' => $entry->is_fixed,
                    'is_installment' => $entry->is_installment,
                    'counts_toward_weekly_spending' => $entry->counts_toward_weekly_spending,
                    'notes' => $entry->notes,
                ]);
            }

            return $recurringEntries->count();
        }, attempts: 5);
    }

    private function assertEmpty(Workspace $workspace, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($workspace->entries()->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->exists()) {
            throw ValidationException::withMessages(['synchronization' => 'Este mês já possui lançamentos e não pode ser sincronizado.']);
        }
    }
}
