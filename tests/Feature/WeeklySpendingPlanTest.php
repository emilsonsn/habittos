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

    public function test_spending_below_the_weekly_limit_does_not_increase_the_adjusted_limit(): void
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
        $this->assertSame(130000, $summary['monthlyLimit']);
        $this->assertSame(32500, $summary['weeklyLimit']);
        $this->assertSame(2, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertSame(32500, $summary['weeklyPlan']['adjustedLimit']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('Limite ajustado')->assertSee('R$ 325,00')
            ->assertDontSee('Editar limite mensal')->assertDontSee('Editar limite semanal');
    }

    public function test_only_excess_from_completed_weeks_reduces_the_adjusted_limit(): void
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
        $this->assertSame(0, $summary['weeklyPlan']['adjustedLimit']);
    }

    public function test_week_three_limit_is_300_after_400_and_600_spending_regardless_of_current_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-01', 40000);
        $this->expense($workspace, '2026-10-08', 60000, ['paid' => false]);
        foreach ([0, 10000, 100000] as $currentSpending) {
            if ($currentSpending > 0) {
                $this->expense($workspace, '2026-10-15', $currentSpending);
            }
            $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
            $this->assertSame(40000, $summary['weeklyLimit']);
            $this->assertSame(2, $summary['weeklyPlan']['remainingWeeks']);
            $this->assertSame(30000, $summary['weeklyPlan']['adjustedLimit']);
        }
        $this->expense($workspace, '2026-10-22', 90000);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('weeklyPlan', fn ($plan) => $plan['adjustedLimit'] === 30000)
            ->assertSee('R$ 300,00')->assertSee('apenas o excesso das semanas encerradas');
    }

    public function test_current_week_remaining_uses_adjusted_limit_and_only_current_variable_expenses(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-01', 40000);
        $this->expense($workspace, '2026-10-08', 60000);
        $this->expense($workspace, '2026-10-15', 10000, ['paid' => false]);
        $this->expense($workspace, '2026-10-22', 90000);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(30000, $summary['weeklyPlan']['adjustedLimit']);
        $this->assertSame(20000, $summary['weeklyPlan']['currentWeekRemaining']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('Restante nesta semana')->assertSee('R$ 200,00');

        $this->expense($workspace, '2026-10-16', 20000);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(0, $summary['weeklyPlan']['currentWeekRemaining']);
        $this->expense($workspace, '2026-10-17', 1000);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(-1000, $summary['weeklyPlan']['currentWeekRemaining']);
        $this->assertSame(30000, $summary['weeklyPlan']['adjustedLimit']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('R$ -10,00');
    }

    public function test_savings_do_not_offset_excess_and_the_current_week_counts_only_when_it_ends(): void
    {
        $workspace = $this->workspace();
        $this->expense($workspace, '2026-10-01', 10000);
        $this->expense($workspace, '2026-10-08', 60001);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 23:59:59'));
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(40000, $summary['weeklyPlan']['adjustedLimit']);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 00:00:00'));
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        // Only the 200.01 excess counts; the 300.00 saved in week one does not offset it.
        $this->assertSame(29999, $summary['weeklyPlan']['adjustedLimit']);
    }

    public function test_live_edits_recalculate_automatic_limits_and_manual_edits_are_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        $workspace = $this->workspace();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertViewHas('weeklyLimit', 40000)
            ->call('startEditingIndicator', 'reserve')->set('indicatorValue', '400')->call('saveIndicator')
            ->assertHasNoErrors()->assertViewHas('weeklyLimit', 30000)
            ->assertViewHas('weeklyPlan', fn ($plan) => $plan['adjustedLimit'] === 30000);
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
        $this->assertSame(40000, $summary['weeklyPlan']['adjustedLimit']);
        $this->assertNull($summary['weeklyPlan']['currentWeekRemaining']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertDontSee('Restante nesta semana');
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(0, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertNull($summary['weeklyPlan']['adjustedLimit']);
        $this->assertNull($summary['weeklyPlan']['currentWeekRemaining']);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertSee('Mês encerrado')->assertDontSee('Restante nesta semana');
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
