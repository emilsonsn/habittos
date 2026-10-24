<?php

namespace Tests\Feature;

use App\Livewire\EntryForm;
use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvestmentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_and_settled_investments_do_not_change_income_expenses_balances_or_spending_limits(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $category = $workspace->categories()->firstOrFail();
        foreach ([['income', 100000, true, false], ['expense', 10000, true, true], ['expense', 2000, false, false]] as [$type, $amount, $paid, $fixed]) {
            $workspace->entries()->create(['description' => $type, 'type' => $type, 'amount_cents' => $amount,
                'date' => '2026-10-10', 'paid' => $paid, 'is_fixed' => $fixed, 'category_id' => $category->id]);
        }
        $workspace->budgets()->create(['month' => '2026-10', 'reserve_cents' => 3000, 'investment_balance_cents' => 2000000]);
        $before = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        foreach ([true, false] as $paid) {
            $workspace->entries()->create(['description' => 'Investimento', 'type' => 'investment', 'amount_cents' => 10000,
                'date' => '2026-10-10', 'paid' => $paid, 'is_fixed' => true, 'category_id' => $category->id]);
        }
        $after = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(20000, $after['invested']);
        foreach (['investmentBalance', 'income', 'expenses', 'fixed', 'fixedIncome', 'balance', 'available', 'projected', 'pending', 'monthlyLimit', 'weeklyLimit', 'weeks', 'weeklyPlan', 'byCategory'] as $key) {
            $this->assertEquals($before[$key], $after[$key], $key.' deve ignorar os lançamentos de investimento.');
        }
    }

    public function test_investment_created_in_form_is_positive_in_both_lists_and_status_does_not_change_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Meu investimento')->set('form.type', 'investment')
            ->set('form.amount', '100')->set('form.date', '2026-10-24')
            ->call('saveAndCreateAnother')->assertHasNoErrors();
        $investment = $workspace->entries()->firstOrFail();
        $this->assertSame(10000, $investment->amount_cents);
        foreach (['finance.investments', 'finance.entries'] as $route) {
            $response = $this->get(route($route, [$workspace, 'month' => '2026-10']))->assertOk()->assertSee('Meu investimento');
            $this->assertMatchesRegularExpression('/\+\s*R\$ 100,00/u', $response->getContent());
            $this->assertDoesNotMatchRegularExpression('/−\s*R\$ 100,00/u', $response->getContent());
        }
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('invested', 10000)->assertViewHas('available', 0)->assertViewHas('projected', 0)
            ->assertViewHas('income', 0)->assertViewHas('expenses', 0)
            ->call('togglePaid', $investment->id)->assertHasNoErrors()->assertViewHas('pending', 0)
            ->assertViewHas('available', 0)->assertViewHas('projected', 0)
            ->call('deleteEntry', $investment->id)->assertHasNoErrors()->assertViewHas('invested', 0)
            ->assertViewHas('available', 0)->assertViewHas('projected', 0);
    }
}
