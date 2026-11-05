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

    public function test_cash_effect_uses_only_current_workspace_cycle_and_withdrawals_restore_cash(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $this->actingAs($owner);
        $workspace->budgets()->create(['month' => '2026-10', 'starts_on' => '2026-10-05', 'reserve_cents' => 3000]);
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
        $workspace->entries()->create(['description' => 'Receita', 'type' => 'income', 'amount_cents' => 100000, 'date' => '2026-10-05', 'paid' => true]);
        $workspace->entries()->create(['description' => 'Despesa', 'type' => 'expense', 'amount_cents' => 10000, 'date' => '2026-10-05', 'paid' => true]);
        $deposit = $workspace->entries()->create(['description' => 'Aporte efetivado', 'type' => 'investment', 'amount_cents' => 30000, 'date' => '2026-10-06', 'paid' => true]);
        $pendingDeposit = $workspace->entries()->create(['description' => 'Aporte pendente', 'type' => 'investment', 'amount_cents' => 10000, 'date' => '2026-10-07', 'paid' => false]);
        $pendingWithdrawal = $workspace->entries()->create(['description' => 'Retirada pendente', 'type' => 'investment', 'amount_cents' => -2000, 'date' => '2026-10-08', 'paid' => false]);
        $withdrawal = $workspace->entries()->create(['description' => 'Retirada efetivada', 'type' => 'investment', 'amount_cents' => -5000, 'date' => '2026-11-02', 'paid' => true]);
        foreach (['2026-10-04', '2026-11-03'] as $date) {
            $workspace->entries()->create(['description' => 'Outro ciclo', 'type' => 'investment', 'amount_cents' => 99999, 'date' => $date, 'paid' => true]);
        }
        $other = app(CreateWorkspace::class)->handle($owner, 'Outro');
        $other->entries()->create(['description' => 'Outro ambiente', 'type' => 'investment', 'amount_cents' => 99999, 'date' => '2026-10-10', 'paid' => true]);

        foreach (['dashboard', 'entries'] as $section) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => $section])
                ->assertViewHas('invested', 33000)->assertViewHas('balance', 65000)
                ->assertViewHas('available', 62000)->assertViewHas('projected', 54000)
                ->assertViewHas('entryBalances', fn ($balances) => $balances[$withdrawal->id]['after'] === 57000);
        }
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('togglePaid', $pendingDeposit->id)->assertViewHas('available', 52000)
            ->call('togglePaid', $pendingWithdrawal->id)->assertViewHas('available', 54000)
            ->call('deleteEntry', $deposit->id)->assertViewHas('available', 84000)->assertViewHas('projected', 84000);
    }

    public function test_pending_and_settled_investments_reduce_balances_without_changing_expense_totals_or_spending_limits(): void
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
        $this->assertSame(20000, $after['investmentBalance']);
        $this->assertSame($before['balance'] - 10000, $after['balance']);
        $this->assertSame($before['available'] - 10000, $after['available']);
        $this->assertSame($before['projected'] - 20000, $after['projected']);
        foreach (['income', 'expenses', 'fixed', 'fixedIncome', 'pending', 'monthlyLimit', 'weeklyLimit', 'weeks', 'weeklyPlan', 'byCategory'] as $key) {
            $this->assertEquals($before[$key], $after[$key], $key.' deve ignorar os lançamentos de investimento.');
        }
    }

    public function test_investment_is_an_outflow_in_entries_and_status_changes_available_balance(): void
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
            $sign = $route === 'finance.entries' ? '−' : '\\+';
            $this->assertMatchesRegularExpression('/'.$sign.'\s*R\$ 100,00/u', $response->getContent());
        }
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('invested', 10000)->assertViewHas('available', -10000)->assertViewHas('projected', -10000)
            ->assertViewHas('income', 0)->assertViewHas('expenses', 0)
            ->call('togglePaid', $investment->id)->assertHasNoErrors()->assertViewHas('pending', 0)
            ->assertViewHas('available', 0)->assertViewHas('projected', -10000)
            ->call('deleteEntry', $investment->id)->assertHasNoErrors()->assertViewHas('invested', 0)
            ->assertViewHas('available', 0)->assertViewHas('projected', 0);
    }
}
