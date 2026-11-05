<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntryRunningBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balances_follow_date_then_id_and_include_pending_entries_before_category_filtering(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        $category = $workspace->categories()->firstOrFail();
        $workspace->budgets()->create(['month' => '2026-10', 'starts_on' => '2026-10-05']);
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
        $create = fn ($description, $type, $amount, $date, $extra = []) => $workspace->entries()->create($extra + ['description' => $description, 'type' => $type, 'amount_cents' => $amount, 'date' => $date]);
        // Insert out of chronological order and include a same-day tie.
        $expense = $create('Cinema', 'expense', 3000, '2026-10-10', ['category_id' => $category->id, 'paid' => false]);
        $income = $create('Salário', 'income', 10000, '2026-10-05', ['paid' => true]);
        $investment = $create('Aporte', 'investment', 5000, '2026-10-10');
        $refund = $create('Estorno', 'expense', -500, '2026-10-10');
        $last = $create('Compra final', 'expense', 9000, '2026-11-02', ['category_id' => $category->id]);
        $create('Anterior', 'income', 99999, '2026-10-04');
        $create('Próximo', 'income', 99999, '2026-11-03');
        $other = app(CreateWorkspace::class)->handle($user, 'Outro');
        $other->entries()->create(['description' => 'Privado', 'type' => 'income', 'amount_cents' => 99999, 'date' => '2026-10-10']);
        $expected = [
            $income->id => ['before' => 0, 'after' => 10000],
            $expense->id => ['before' => 10000, 'after' => 7000],
            $investment->id => ['before' => 7000, 'after' => 2000],
            $refund->id => ['before' => 2000, 'after' => 2500],
            $last->id => ['before' => 2500, 'after' => -6500],
        ];
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame($expected, $summary['entryBalances']);
        $this->assertSame([$last->id, $refund->id, $investment->id, $expense->id, $income->id], $summary['entries']->modelKeys());

        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'entries'])
            ->assertSee('<th scope="col" class="number">Saldo</th>', false)
            ->assertSee('Antes: R$ 25,00')->assertSee('R$ -65,00')
            ->set('categoryFilter', [(string) $category->id])
            ->assertDontSee('Salário')->assertSee('Cinema')->assertSee('Compra final')
            ->assertViewHas('entryBalances', $expected)
            ->call('togglePaid', $expense->id)->assertViewHas('entryBalances', $expected)
            ->call('deleteEntry', $expense->id)
            ->assertViewHas('entryBalances', fn ($balances) => $balances[$last->id] === ['before' => 5500, 'after' => -3500])
            ->call('openMonth', '2026-11')
            ->assertViewHas('entryBalances', fn ($balances) => count($balances) === 1 && array_values($balances)[0] === ['before' => 0, 'after' => 99999]);
    }
}
