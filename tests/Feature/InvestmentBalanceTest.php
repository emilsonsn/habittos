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

class InvestmentBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_uses_all_dates_and_subtracts_withdrawals_without_affecting_financial_results(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        foreach ([['2025-01-01', 100000], ['2026-10-10', -12345], ['2027-01-01', 50000]] as [$date, $amount]) {
            $workspace->entries()->create(['description' => 'Investimento', 'type' => 'investment', 'amount_cents' => $amount, 'date' => $date]);
        }
        $other = app(CreateWorkspace::class)->handle($user, 'Outro');
        $other->entries()->create(['description' => 'Outro ambiente', 'type' => 'investment', 'amount_cents' => 999999, 'date' => '2026-10-10']);
        foreach (['2024-01', '2026-10', '2027-01', '2028-01'] as $month) {
            $summary = app(FinancialSummary::class)->forMonth($workspace, $month);
            $this->assertSame(137655, $summary['investmentBalance']);
            foreach (['income', 'expenses', 'balance', 'available', 'projected', 'pending', 'monthlyLimit', 'weeklyLimit'] as $key) {
                $this->assertSame(0, $summary[$key], $key);
            }
        }
        $this->assertSame(-12345, app(FinancialSummary::class)->forMonth($workspace, '2026-10')['invested']);
    }

    public function test_negative_investments_keep_exact_cents_and_edit_and_delete_recalculate_the_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        foreach (['-12.34' => -1234, '-0.50' => -50, '-0.01' => -1] as $amount => $cents) {
            Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
                ->set('form.type', 'investment')->set('form.description', 'Retirada '.$amount)
                ->set('form.amount', $amount)->set('form.date', '2026-10-24')->call('save')->assertHasNoErrors();
            $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'description' => 'Retirada '.$amount, 'amount_cents' => $cents]);
        }
        $this->assertSame(-1285, app(FinancialSummary::class)->forMonth($workspace, '2026-10')['investmentBalance']);
        foreach (['finance.investments', 'finance.entries'] as $route) {
            $response = $this->get(route($route, [$workspace, 'month' => '2026-10']))->assertOk();
            $this->assertMatchesRegularExpression('/−\s*R\$ 12,34/u', $response->getContent());
            $this->assertDoesNotMatchRegularExpression('/\+\s*R\$ -/u', $response->getContent());
        }
        $entry = $workspace->entries()->where('amount_cents', -1234)->firstOrFail();
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->assertSet('form.amount', '-12.34')->set('form.amount', '20')->call('save')->assertHasNoErrors();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('investmentBalance', 1949)->call('deleteEntry', $entry->id)
            ->assertHasNoErrors()->assertViewHas('investmentBalance', -51)->assertViewHas('available', 0);
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_http_accepts_withdrawals_but_rejects_zero_overflow_and_negative_income_or_expenses(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        $data = ['description' => 'Retirada', 'type' => 'investment', 'amount' => '-0.50', 'date' => '2026-10-24'];
        $this->post(route('entries.store', $workspace), $data)->assertSessionHasNoErrors()->assertRedirect();
        $entry = $workspace->entries()->firstOrFail();
        $this->assertSame(-50, $entry->amount_cents);
        $this->put(route('entries.update', [$workspace, $entry]), array_replace($data, ['amount' => '-12.34']))->assertSessionHasNoErrors();
        $this->assertSame(-1234, $entry->fresh()->amount_cents);
        foreach (['expense', 'income'] as $type) {
            $this->post(route('entries.store', $workspace), array_replace($data, ['type' => $type]))->assertSessionHasErrors('amount');
            Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
                ->set('form.type', $type)->set('form.description', 'Inválido')->set('form.amount', '-0.50')
                ->call('save')->assertHasErrors('form.amount');
        }
        foreach (['0', '-0.00', '-1.001', '-1000000000', '1000000000'] as $amount) {
            $this->post(route('entries.store', $workspace), array_replace($data, ['amount' => $amount]))->assertSessionHasErrors('amount');
            Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
                ->set('form.type', 'investment')->set('form.description', 'Inválido')->set('form.amount', $amount)
                ->call('save')->assertHasErrors('form.amount');
        }
        $this->assertDatabaseCount('entries', 1);
    }
}
