<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WeeklySpendingPlanTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $workspace->entries()->create(['description' => 'Receita', 'type' => 'income', 'amount_cents' => 160000, 'date' => '2026-10-01', 'paid' => true]);
        // Values from the old manual limits must no longer affect the calculation.
        $workspace->budgets()->create(['month' => '2026-10', 'weekly_limit_cents' => 99999, 'monthly_limit_cents' => 999999]);
        $this->actingAs($owner);

        return $workspace;
    }

    private function expense(Workspace $workspace, string $date, int $amount, array $attributes = []): void
    {
        $workspace->entries()->create($attributes + ['description' => 'Mercado', 'type' => 'expense', 'amount_cents' => $amount, 'date' => $date, 'paid' => true, 'is_fixed' => false]);
    }

    public function test_base_limits_exclude_variable_expenses_while_adjusted_limit_deducts_them(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-01', 20000);
        $this->expense($workspace, '2026-10-08', 10000, ['paid' => false]);
        $this->expense($workspace, '2026-10-15', 5000);
        $this->expense($workspace, '2026-10-31', 5000);
        $this->expense($workspace, '2026-10-08', 20000, ['is_fixed' => true]);
        $this->expense($workspace, '2026-10-08', 10000, ['type' => 'investment']);
        $workspace->budgets()->where('month', '2026-10')->update(['reserve_cents' => 10000, 'investment_balance_cents' => 500000]);
        $this->expense($workspace, '2026-09-08', 50000);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->expense($other, '2026-10-08', 50000);

        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(120000, $summary['monthlyLimit']);
        $this->assertSame(30000, $summary['weeklyLimit']);
        $this->assertSame(2, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertSame(40000, $summary['weeklyPlan']['adjustedLimit']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('Limite ajustado')->assertSee('R$ 300,00')->assertSee('R$ 400,00')
            ->assertDontSee('Editar limite mensal')->assertDontSee('Editar limite semanal');
    }

    public function test_current_week_expenses_reduce_only_adjusted_limit_without_double_subtraction(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));
        $workspace = $this->workspace();
        foreach (['2026-10-01' => 56535, '2026-10-08' => 77351, '2026-10-15' => 14563, '2026-10-22' => 2000, '2026-10-31' => 3000] as $date => $amount) {
            $this->expense($workspace, $date, $amount);
        }
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertCount(4, $summary['weeks']);
        $this->assertSame(5000, $summary['weeks']->last()['spent']);
        $this->assertSame(31, $summary['weeks']->last()['endDay']);
        $this->assertSame(40000, $summary['weeklyLimit']);
        $this->assertSame(1, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertSame(6551, $summary['weeklyPlan']['adjustedLimit']);
    }

    public function test_live_edits_recalculate_automatic_limits_and_manual_edits_are_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        $workspace = $this->workspace();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('weeklyLimit', 40000)
            ->call('startEditingIndicator', 'reserve')->set('indicatorValue', '400')->call('saveIndicator')
            ->assertHasNoErrors()->assertViewHas('weeklyLimit', 30000)
            ->assertViewHas('weeklyPlan', fn ($plan) => $plan['adjustedLimit'] === 40000);
        foreach (['weekly_limit', 'monthly_limit'] as $indicator) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
                ->call('startEditingIndicator', $indicator)->assertStatus(422);
        }
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'weekly_limit_cents' => 99999, 'monthly_limit_cents' => 999999]);
    }

    public function test_negative_balance_never_suggests_negative_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-01', 180000);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(-20000, $summary['available']);
        $this->assertSame(40000, $summary['weeklyLimit']);
        $this->assertSame(0, $summary['weeklyPlan']['adjustedLimit']);
        $this->expense($workspace, '2026-10-02', 170000, ['is_fixed' => true]);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(0, $summary['monthlyLimit']);
        $this->assertSame(0, $summary['weeklyLimit']);
    }

    public function test_navigation_handles_past_future_and_empty_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00'));
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-31', 25000);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(4, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertSame(33750, $summary['weeklyPlan']['adjustedLimit']);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(0, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertNull($summary['weeklyPlan']['adjustedLimit']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('Mês encerrado');
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-11');
        $this->assertSame(0, $summary['weeklyLimit']);
        $this->assertSame(0, $summary['weeklyPlan']['adjustedLimit']);
    }

    public function test_february_still_has_four_weeks(): void
    {
        $workspace = $this->workspace();
        foreach (['2027-02' => 28, '2028-02' => 29] as $month => $lastDay) {
            $this->expense($workspace, "$month-$lastDay", 1000);
            $summary = app(FinancialSummary::class)->forMonth($workspace, $month);
            $this->assertCount(4, $summary['weeks']);
            $this->assertSame($lastDay, $summary['weeks']->last()['endDay']);
            $this->assertSame(1000, $summary['weeks']->last()['spent']);
        }
    }
}
