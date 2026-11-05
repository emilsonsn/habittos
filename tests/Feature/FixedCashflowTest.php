<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use App\Services\OpenFinancialMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FixedCashflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_sections_offer_confirmed_deletion_and_preserve_other_months(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $this->actingAs($owner);

        foreach (['income' => 'fixed-income', 'expense' => 'fixed-expenses'] as $type => $section) {
            $entry = $workspace->entries()->create(['description' => 'Fixo '.$type, 'type' => $type, 'amount_cents' => 10000, 'date' => '2026-10-10', 'is_fixed' => true]);
            $nextMonth = $workspace->entries()->create(['description' => 'Fixo '.$type, 'type' => $type, 'amount_cents' => 10000, 'date' => '2026-11-10', 'is_fixed' => true]);

            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => $section])
                ->assertSee('wire:click="deleteEntry('.$entry->id.')"', false)
                ->assertSee('data-confirm-title="Excluir lançamento?"', false)
                ->assertSee('data-confirm-subject="'.$entry->description.'"', false)
                ->call('deleteEntry', $entry->id)->assertHasNoErrors()
                ->assertSee('Lançamento excluído.')
                ->assertViewHas($type === 'income' ? 'fixedIncome' : 'fixed', 0);

            $this->assertModelMissing($entry);
            $this->assertModelExists($nextMonth);
        }
    }

    public function test_fixed_deletion_requires_its_own_permission(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['permissions' => ['finance.view', 'finance.update']]);
        $this->actingAs($member);

        foreach (['income' => 'fixed-income', 'expense' => 'fixed-expenses'] as $type => $section) {
            $entry = $workspace->entries()->create(['description' => 'Fixo '.$type, 'type' => $type, 'amount_cents' => 10000, 'date' => '2026-10-10', 'is_fixed' => true]);
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => $section])
                ->assertSee('wire:click="editEntry('.$entry->id.')"', false)
                ->assertDontSee('wire:click="deleteEntry(', false)
                ->call('deleteEntry', $entry->id)->assertForbidden();
            $this->assertModelExists($entry);
        }

        $workspace->members()->updateExistingPivot($member->id, ['permissions' => ['finance.view', 'finance.delete']]);
        foreach (['income' => 'fixed-income', 'expense' => 'fixed-expenses'] as $type => $section) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => $section])
                ->assertSee('wire:click="deleteEntry(', false)
                ->assertDontSee('wire:click="editEntry(', false);
        }
    }

    public function test_salary_and_expenses_are_copied_independently_and_begin_pending(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        foreach (['income', 'expense', 'investment'] as $type) {
            $workspace->entries()->create(['description' => $type, 'type' => $type, 'amount_cents' => 100000, 'date' => '2027-01-31', 'paid' => true, 'is_fixed' => true]);
        }
        $opener = app(OpenFinancialMonth::class);
        $this->assertSame(2, $opener->handle($workspace, '2027-02', '2027-02-05', '2027-02-28'));
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2027-02');
        $this->assertSame(100000, $summary['fixedIncome']);
        $this->assertSame(100000, $summary['fixed']);
        $this->assertSame(0, $summary['invested']);
        $this->assertSame(0, $summary['balance']);
        $this->assertCount(2, $summary['entries']);
        foreach ($summary['entries'] as $entry) {
            $this->assertFalse($entry->paid);
            $this->assertSame('2027-02-05', $entry->date->format('Y-m-d'));
        }
        $summary['entries']->firstWhere('type', 'income')->update(['amount_cents' => 120000]);
        $january = app(FinancialSummary::class)->forMonth($workspace, '2027-01');
        $this->assertSame(100000, $january['fixedIncome']);
    }

    public function test_accumulated_investments_do_not_count_as_monthly_outflows(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $workspace->entries()->create(['description' => 'Salário', 'type' => 'income', 'amount_cents' => 300000, 'date' => '2026-10-01', 'paid' => true, 'is_fixed' => true]);
        $workspace->budgets()->create(['month' => '2026-10', 'investment_balance_cents' => 2000000]);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(0, $summary['investmentBalance']);
        $this->assertSame(0, $summary['invested']);
        $this->assertSame(300000, $summary['projected']);
        $this->assertSame(300000, $summary['balance']);
        $this->actingAs($owner)->get(route('finance.index', [$workspace, 'month' => '2026-10']))
            ->assertOk()->assertSeeInOrder(['Receitas fixas', 'Salário', 'Gastos fixos'])
            ->assertSee('Saldo total');
    }
}
