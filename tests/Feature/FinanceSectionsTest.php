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
