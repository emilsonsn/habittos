<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_indicator_preserves_other_values_and_other_months(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $workspace->budgets()->create(['month' => '2026-10', 'weekly_limit_cents' => 40000, 'reserve_cents' => 50000, 'investment_balance_cents' => 100000]);
        $workspace->budgets()->create(['month' => '2026-11', 'monthly_limit_cents' => 200000]);
        $this->actingAs($user);
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertDontSee('Editar limite mensal')->assertDontSee('Como o mês termina?')
            ->call('startEditingIndicator', 'reserve')->set('indicatorValue', '1600.55')
            ->call('saveIndicator')->assertHasNoErrors()->assertSet('editingIndicator', null);
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2026-10', 'monthly_limit_cents' => null, 'weekly_limit_cents' => 40000, 'reserve_cents' => 160055, 'investment_balance_cents' => 100000]);
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2026-11', 'monthly_limit_cents' => 200000]);
        $component->call('startEditingIndicator', 'reserve')->set('indicatorValue', '-10')->call('saveIndicator')->assertHasErrors('indicatorValue');
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2026-10', 'reserve_cents' => 160055]);
        $component->set('indicatorValue', '25.01')->call('cancelIndicator')->assertSet('editingIndicator', null);
        $component->call('startEditingIndicator', 'reserve')->assertSet('indicatorValue', '1600.55')
            ->call('openMonth', '2026-11')->assertSet('editingIndicator', null)->assertSet('indicatorValue', '');
    }

    public function test_reserve_updates_available_balance_while_investment_stock_does_not(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $workspace->entries()->create(['description' => 'Receita', 'type' => 'income', 'amount_cents' => 100000, 'date' => '2026-10-01', 'paid' => true]);
        $this->actingAs($user);
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('startEditingIndicator', 'reserve')->set('indicatorValue', '250.25')->call('saveIndicator')->assertHasNoErrors();
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(74975, $summary['available']);
        $workspace->entries()->create(['description' => 'Aporte', 'type' => 'investment', 'amount_cents' => 200000, 'date' => '2026-09-01']);
        $component->call('startEditingIndicator', 'investment_balance')->assertStatus(422);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(200000, $summary['investmentBalance']);
        $this->assertSame(74975, $summary['available']);
    }

    public function test_read_only_members_cannot_edit_or_save_indicators(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertDontSee('Editar limite mensal')->call('startEditingIndicator', 'reserve')->assertForbidden();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('saveIndicator')->assertForbidden();
        $this->assertDatabaseCount('budgets', 0);
    }
}
