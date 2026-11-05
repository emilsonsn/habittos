<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WeeklySpendingDialogTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return app(CreateWorkspace::class)->handle($owner, 'Casa');
    }

    public function test_dialog_uses_the_same_entries_and_total_as_the_chart_with_cycle_and_workspace_filters(): void
    {
        $workspace = $this->workspace();
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
        $workspace->budgets()->create(['month' => '2026-12', 'starts_on' => '2026-12-03']);
        $included = [];
        foreach ([['Mercado', 1000, true, false], ['Farmácia pendente', 2500, false, false], ['Parcela semanal', 500, true, true]] as [$description, $amount, $paid, $installment]) {
            $included[] = $workspace->entries()->create(['description' => $description, 'type' => 'expense', 'date' => '2026-11-03', 'amount_cents' => $amount, 'paid' => $paid, 'is_installment' => $installment])->id;
        }
        $excluded = [];
        foreach ([['Fixa', 'expense', true, true, '2026-11-03'], ['Fora do semanal', 'expense', false, false, '2026-11-03'], ['Receita', 'income', false, true, '2026-11-03'], ['Aporte', 'investment', false, true, '2026-11-03'], ['Segunda semana', 'expense', false, true, '2026-11-10'], ['Antes do ciclo', 'expense', false, true, '2026-11-02'], ['Próximo ciclo', 'expense', false, true, '2026-12-03']] as [$description, $type, $fixed, $weekly, $date]) {
            $excluded[] = $workspace->entries()->create(['description' => $description, 'type' => $type, 'is_fixed' => $fixed, 'counts_toward_weekly_spending' => $weekly, 'date' => $date, 'amount_cents' => 9999])->id;
        }
        $lastWeek = $workspace->entries()->create(['description' => 'Fim do ciclo', 'type' => 'expense', 'date' => '2026-12-02', 'amount_cents' => 1500]);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $excluded[] = $other->entries()->create(['description' => 'Privada', 'type' => 'expense', 'date' => '2026-11-03', 'amount_cents' => 9999])->id;

        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->assertDontSee('id="week-entries-title"', false)
            ->assertSee('wire:click="showWeekEntries(1)"', false)
            ->call('showWeekEntries', 1)->assertSet('selectedWeek', 1)
            ->assertSee('Lançamentos da semana 1')->assertSee('Mercado')->assertSee('Farmácia pendente')->assertSee('Parcela semanal')
            ->assertSee('Total da semana')->assertSee('R$ 40,00')
            ->assertViewHas('selectedWeekDetails', fn ($week) => $week['entries']->modelKeys() === array_reverse($included) && $week['spent'] === 4000 && $week['spent'] === $week['entries']->sum('amount_cents'));
        foreach ($excluded as $id) {
            $component->assertDontSee('wire:key="week-entry-'.$id.'"', false);
        }
        $component->call('showWeekEntries', 4)->assertSee('Fim do ciclo')
            ->assertViewHas('selectedWeekDetails', fn ($week) => $week['entries']->modelKeys() === [$lastWeek->id] && $week['spent'] === 1500)
            ->call('closeWeekEntries')->assertSet('selectedWeek', null)->assertDontSee('id="week-entries-title"', false);
    }

    public function test_empty_weeks_show_an_empty_state_and_changing_month_closes_the_dialog(): void
    {
        $workspace = $this->workspace();
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('showWeekEntries', 2)->assertSee('Nenhum lançamento compõe os gastos desta semana.')
            ->assertViewHas('selectedWeekDetails', fn ($week) => $week['entries']->isEmpty() && $week['spent'] === 0)
            ->call('openMonth', '2026-12')->assertSet('selectedWeek', null)->assertDontSee('id="week-entries-title"', false)
            ->call('showWeekEntries', 1)->set('month', '2027-01')->assertSet('selectedWeek', null);
    }

    public function test_week_details_require_only_finance_view_and_recheck_access(): void
    {
        $workspace = $this->workspace();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('showWeekEntries', 1)->assertSee('Lançamentos da semana 1');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => []]);
        $component->call('showWeekEntries', 2)->assertForbidden();
    }

    public function test_invalid_week_numbers_are_rejected(): void
    {
        $workspace = $this->workspace();
        foreach ([0, 5] as $number) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
                ->call('showWeekEntries', $number)->assertNotFound();
        }
    }
}
