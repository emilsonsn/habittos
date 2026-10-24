<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreateWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_greeting_follows_fortaleza_time_and_the_home_has_three_placeholders(): void
    {
        $user = User::factory()->create(['name' => 'Pessoa']);
        app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        foreach (['04:59' => 'Boa noite', '05:00' => 'Bom dia', '11:59' => 'Bom dia', '12:00' => 'Boa tarde', '17:59' => 'Boa tarde', '18:00' => 'Boa noite'] as $time => $greeting) {
            $this->travelTo(CarbonImmutable::parse('2026-10-23 '.$time, 'America/Fortaleza'));
            $this->get(route('home'))->assertOk()->assertSee($greeting.', Pessoa')
                ->assertSee('Indicador 2')->assertSee('Indicador 3')->assertSee('Indicador 4');
        }
        $this->travelTo(CarbonImmutable::parse('2026-10-24 01:00', 'UTC'));
        $this->get(route('home'))->assertSee('Boa noite, Pessoa');
    }

    public function test_investment_card_sums_all_months_and_withdrawals_and_ignores_manual_balances(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $workspace->budgets()->create(['month' => '2026-10', 'investment_balance_cents' => 1000000]);
        foreach ([['2025-01-01', 100000, true], ['2026-10-01', 50000, false], ['2026-11-01', -12345, true]] as [$date, $amount, $paid]) {
            $workspace->entries()->create(['description' => 'Investimento', 'type' => 'investment', 'amount_cents' => $amount, 'date' => $date, 'paid' => $paid]);
        }
        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('Investimentos acumulados')->assertSee('R$ 1.376,55')->assertSee('Todo o histórico do ambiente')
            ->assertDontSee('R$ 10.000,00')->assertDontSee('Último registro');
        $workspace->entries()->where('type', 'investment')->delete();
        $this->get(route('home'))->assertSee('R$ 0,00');
    }

    public function test_home_keeps_investments_scoped_to_the_selected_authorized_workspace(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Primeiro');
        $second = app(CreateWorkspace::class)->handle($user, 'Segundo');
        $private = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Privado');
        foreach ([[$first, 10000], [$second, 20000], [$private, 999999]] as [$workspace, $balance]) {
            $workspace->entries()->create(['description' => 'Investimento', 'type' => 'investment', 'amount_cents' => $balance, 'date' => '2026-10-01']);
        }
        $this->actingAs($user)->withSession(['active_workspace_id' => $first->id])
            ->get(route('home'))->assertSee('R$ 100,00')->assertDontSee('R$ 200,00')->assertDontSee('R$ 9.999,99');
        $this->withSession(['active_workspace_id' => $second->id])->get(route('home'))
            ->assertSee('R$ 200,00')->assertDontSee('R$ 100,00');
        $this->withSession(['active_workspace_id' => $private->id])->get(route('home'))
            ->assertDontSee('R$ 9.999,99')->assertSessionHas('active_workspace_id', $first->id);
    }

    public function test_missing_balances_empty_membership_and_missing_finance_permission_have_clear_empty_states(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Selecione ou crie um ambiente');
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->get(route('home'))->assertSee('R$ 0,00');
        $workspace->entries()->create(['description' => 'Investimento', 'type' => 'investment', 'amount_cents' => 123456, 'date' => '2026-10-01']);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['goals.view']]);
        $this->actingAs($reader)->get(route('home'))
            ->assertOk()->assertSee('Sem acesso ao financeiro')->assertDontSee('R$ 1.234,56');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view']]);
        $this->get(route('home'))->assertSee('R$ 1.234,56');
        $workspace->members()->detach($reader);
        $this->get(route('home'))->assertDontSee('R$ 1.234,56')->assertSee('Selecione ou crie um ambiente');
    }
}
