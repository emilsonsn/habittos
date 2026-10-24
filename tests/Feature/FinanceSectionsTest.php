<?php

namespace Tests\Feature;

use App\Enums\FinanceSection;
use App\Livewire\FinanceDashboard;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Casa');
    }

    public function test_sections_render_their_own_records_and_dashboard_has_a_pie_instead_of_transactions(): void
    {
        $workspace = $this->workspace();
        foreach ([['Salário fixo', 'income', true], ['Aluguel fixo', 'expense', true], ['Compra variável', 'expense', false]] as [$description, $type, $fixed]) {
            $workspace->entries()->create(['description' => $description, 'type' => $type, 'is_fixed' => $fixed, 'amount_cents' => 10000, 'date' => '2026-10-10']);
        }
        foreach (FinanceSection::cases() as $section) {
            $this->get(route($section->routeName(), [$workspace, 'month' => '2026-10']))
                ->assertOk()->assertSee('aria-current="page"', false)
                ->assertSee(route($section->routeName(), [$workspace, 'month' => '2026-10']), false);
        }
        $this->get(route('finance.index', [$workspace, 'month' => '2026-10']))
            ->assertSee('Gráfico de pizza')->assertSee('100,0%')
            ->assertDontSee('class="panel transactions"', false)->assertDontSee('Compra variável');
        $this->get(route('finance.fixed-income', [$workspace, 'month' => '2026-10']))
            ->assertSee('Salário fixo')->assertDontSee('Aluguel fixo')->assertDontSee('Compra variável');
        $this->get(route('finance.fixed-expenses', [$workspace, 'month' => '2026-10']))
            ->assertSee('Aluguel fixo')->assertDontSee('Salário fixo')->assertDontSee('Compra variável');
        $this->get(route('finance.entries', [$workspace, 'month' => '2026-10']))
            ->assertSee('Salário fixo')->assertSee('Aluguel fixo')->assertSee('Compra variável');
        $this->assertDatabaseCount('entries', 3);
        $this->assertDatabaseCount('financial_months', 0);
    }

    public function test_shared_component_keeps_section_on_month_change_and_updates_records_and_chart(): void
    {
        $workspace = $this->workspace();
        $firstCategory = $workspace->categories()->firstOrFail();
        $secondCategory = $workspace->categories()->where('id', '!=', $firstCategory->id)->firstOrFail();
        $entry = $workspace->entries()->create(['description' => 'Despesa outubro', 'category_id' => $firstCategory->id, 'type' => 'expense', 'amount_cents' => 30000, 'date' => '2026-10-10']);
        $workspace->entries()->create(['description' => 'Outra despesa outubro', 'category_id' => $secondCategory->id, 'type' => 'expense', 'amount_cents' => 10000, 'date' => '2026-10-11']);
        $workspace->entries()->create(['description' => 'Despesa novembro', 'type' => 'expense', 'amount_cents' => 1000, 'date' => '2026-11-10']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('75,0%')->assertSee('25,0%')->assertSee('R$ 400,00')
            ->call('deleteEntry', $entry->id)->assertSee('100,0%')->assertDontSee('75,0%');
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'entries'])
            ->assertSee('Outra despesa outubro')->assertDontSee('Despesa novembro')
            ->call('openMonth', '2026-11')->assertSet('section', 'entries')
            ->assertSee('Despesa novembro')->assertDontSee('Outra despesa outubro')
            ->assertDispatched('financial-month-changed', month: '2026-11');
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-12'])
            ->assertDontSee('conic-gradient')->assertSee('As categorias aparecem conforme você registra despesas.');
    }

    public function test_investments_reuse_entry_actions_and_follow_cycle_and_workspace_filters(): void
    {
        $workspace = $this->workspace();
        $workspace->budgets()->create(['month' => '2026-10', 'starts_on' => '2026-10-05']);
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
        $investment = $workspace->entries()->create(['description' => 'Aporte outubro', 'type' => 'investment', 'amount_cents' => 12345, 'date' => '2026-11-02', 'paid' => false]);
        $nextInvestment = $workspace->entries()->create(['description' => 'Aporte novembro', 'type' => 'investment', 'amount_cents' => 20000, 'date' => '2026-11-03']);
        foreach (['income', 'expense'] as $type) {
            $workspace->entries()->create(['description' => 'Registro '.$type, 'type' => $type, 'amount_cents' => 10000, 'date' => '2026-10-10']);
        }
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $otherInvestment = $other->entries()->create(['description' => 'Aporte outro ambiente', 'type' => 'investment', 'amount_cents' => 30000, 'date' => '2026-10-10']);

        $this->get(route('finance.investments', [$workspace, 'month' => '2026-10']))
            ->assertOk()->assertSee('Investimentos do mês')->assertSee('Aporte outubro')->assertSee('R$ 123,45')
            ->assertSee('wire:click="editEntry('.$investment->id.')"', false)
            ->assertSee('wire:click="createEntry"', false)
            ->assertDontSee('Aporte novembro')->assertDontSee('Registro income')->assertDontSee('Registro expense')
            ->assertDontSee('Aporte outro ambiente');
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'investments'])
            ->call('togglePaid', $investment->id)->assertHasNoErrors()->assertSee('Efetivado');
        $this->delete(route('entries.destroy', [$workspace, $otherInvestment]))->assertNotFound();
        $this->assertTrue($investment->fresh()->paid);
        $this->assertNotNull($otherInvestment->fresh());
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'investments'])
            ->call('deleteEntry', $investment->id)->assertHasNoErrors()->assertSee('Nenhum investimento neste mês.')
            ->call('openMonth', '2026-11')->assertSet('section', 'investments')
            ->assertSee('Aporte novembro')->assertDontSee('Aporte outubro');
        $this->assertNull($investment->fresh());
        $this->assertNotNull($nextInvestment->fresh());
    }

    public function test_investments_are_read_only_without_write_permissions(): void
    {
        $workspace = $this->workspace();
        $investment = $workspace->entries()->create(['description' => 'Aporte visível', 'type' => 'investment', 'amount_cents' => 10000, 'date' => '2026-10-10']);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader)->get(route('finance.investments', [$workspace, 'month' => '2026-10']))
            ->assertOk()->assertSee('Aporte visível')->assertDontSee('Novo lançamento')
            ->assertDontSee('wire:click="editEntry(', false)->assertDontSee('wire:click="togglePaid(', false)
            ->assertDontSee('wire:click="deleteEntry(', false);
        foreach (['togglePaid', 'deleteEntry'] as $action) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'investments'])
                ->call($action, $investment->id)->assertForbidden();
        }
        $this->assertNotNull($investment->fresh());
    }

    public function test_every_section_requires_finance_permission_and_workspace_membership(): void
    {
        $workspace = $this->workspace();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        foreach (FinanceSection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertOk()->assertDontSee('Novo lançamento');
        }
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'entries'])
            ->call('deleteEntry', 999)->assertForbidden();
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => []]);
        foreach (FinanceSection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertForbidden();
        }
        $workspace->members()->detach($reader);
        foreach (FinanceSection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertNotFound();
        }
        $this->assertDatabaseCount('financial_months', 0);
    }

    public function test_workspace_switch_keeps_financial_section_and_month(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        foreach (FinanceSection::cases() as $section) {
            Livewire::test(WorkspaceSwitcher::class, ['activeId' => $workspace->id, 'module' => $section->routeName(), 'month' => '2026-10'])
                ->call('selectWorkspace', $other->id)
                ->assertRedirect(route($section->routeName(), [$other, 'month' => '2026-10']));
            $this->post(route('workspaces.select'), ['workspace_id' => $other->id, 'module' => $section->routeName(), 'month' => '2026-10'])
                ->assertRedirect(route($section->routeName(), [$other, 'month' => '2026-10']));
        }
    }
}
