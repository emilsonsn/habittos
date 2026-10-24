<?php

namespace Tests\Feature;

use App\Livewire\EntryForm;
use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\FinancialCalendar;
use App\Services\FinancialSummary;
use App\Services\OpenFinancialMonth;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return app(CreateWorkspace::class)->handle($owner, 'Casa');
    }

    private function configure(Workspace $workspace): void
    {
        $workspace->budgets()->create(['month' => '2026-10', 'starts_on' => '2026-10-05']);
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
    }

    private function entry(Workspace $workspace, string $date, int $amount = 1000, array $attributes = []): void
    {
        $workspace->entries()->create($attributes + ['description' => $date, 'date' => $date, 'amount_cents' => $amount, 'type' => 'expense', 'paid' => true, 'is_fixed' => false]);
    }

    public function test_default_is_the_first_day_including_year_changes_and_leap_years(): void
    {
        $workspace = $this->workspace();
        $calendar = app(FinancialCalendar::class);
        foreach (['2026-12' => ['2026-12-01', '2027-01-01'], '2028-02' => ['2028-02-01', '2028-03-01']] as $month => [$start, $end]) {
            $period = $calendar->period($workspace, $month);
            $this->assertSame($start, $period['start']->toDateString());
            $this->assertSame($end, $period['end']->toDateString());
        }
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_custom_cycles_are_contiguous_and_all_totals_and_weeks_use_their_boundaries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));
        $workspace = $this->workspace();
        $this->configure($workspace);
        foreach (['2026-10-04', '2026-10-05', '2026-10-11', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02', '2026-11-03'] as $date) {
            $this->entry($workspace, $date);
        }
        $this->entry($workspace, '2026-10-05', 100000, ['type' => 'income']);
        $this->entry($workspace, '2026-11-02', 5000, ['type' => 'investment']);
        $workspace->budgets()->where('month', '2026-10')->update(['reserve_cents' => 10000]);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(6000, $summary['expenses']);
        $this->assertSame(79000, $summary['available']);
        $this->assertSame(21250, $summary['weeklyLimit']);
        $this->assertSame([2000, 1000, 1000, 2000], $summary['weeks']->pluck('spent')->all());
        $this->assertSame('05/10–11/10', $summary['weeks']->first()['dateLabel']);
        $this->assertSame('26/10–02/11', $summary['weeks']->last()['dateLabel']);
        $this->assertSame(1, $summary['weeklyPlan']['remainingWeeks']);
        $this->assertSame(21250, $summary['weeklyPlan']['adjustedLimit']);
        $this->assertSame(19250, $summary['weeklyPlan']['currentWeekRemaining']);
        $calendar = app(FinancialCalendar::class);
        $this->assertSame('2026-09', $calendar->monthForDate($workspace, '2026-10-04'));
        $this->assertSame('2026-10', $calendar->monthForDate($workspace, '2026-11-02'));
        $this->assertSame('2026-11', $calendar->monthForDate($workspace, '2026-11-03'));
        $september = app(FinancialSummary::class)->forMonth($workspace, '2026-09');
        $this->assertSame(1000, $september['expenses']);
        $this->assertSame(1000, app(FinancialSummary::class)->forMonth($workspace, '2026-11')['expenses']);
        $this->assertSame($summary['period']['start']->toDateString(), $september['period']['end']->toDateString());
    }

    public function test_editing_the_cycle_is_scoped_validated_and_preserves_entries_and_budget(): void
    {
        $workspace = $this->workspace();
        $workspace->budgets()->create(['month' => '2026-10', 'reserve_cents' => 12345]);
        $this->entry($workspace, '2026-10-02');
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('startEditingCycle')->assertSet('cycleStartInput', '2026-10-01')
            ->set('cycleStartInput', '2026-11-03')->call('saveCycle')->assertHasErrors('cycleStartInput')
            ->set('cycleStartInput', '2026-10-05')->call('saveCycle')->assertHasNoErrors()
            ->assertSet('editingCycle', false)->assertSee('05/10/2026 a 31/10/2026')
            ->assertViewHas('expenses', 0);
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2026-10', 'starts_on' => '2026-10-05 00:00:00', 'reserve_cents' => 12345]);
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'date' => '2026-10-02 00:00:00']);
        $this->assertDatabaseCount('financial_months', 0);
        $this->assertSame('2026-10-01', app(FinancialCalendar::class)->startsOn($other, '2026-10')->toDateString());
        $component->call('startEditingCycle')->set('cycleStartInput', '2026-10-09')->call('openMonth', '2026-11')
            ->assertSet('editingCycle', false)->assertSet('cycleStartInput', '')
            ->call('startEditingCycle')->assertSet('cycleStartInput', '2026-11-01');
    }

    public function test_cycle_editor_saves_start_and_inclusive_end_preserving_neighbor_budget_values(): void
    {
        $workspace = $this->workspace();
        $workspace->budgets()->create(['month' => '2026-11', 'reserve_cents' => 12345]);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('startEditingCycle')->assertSet('cycleEndInput', '2026-10-31')
            ->set('cycleStartInput', '2026-10-05')->set('cycleEndInput', '2026-11-03')
            ->call('saveCycle')->assertHasNoErrors()->assertSet('editingCycle', false)
            ->assertSee('05/10/2026 a 03/11/2026');
        $calendar = app(FinancialCalendar::class);
        $this->assertSame('2026-10-05', $calendar->startsOn($workspace, '2026-10')->toDateString());
        $this->assertSame('2026-11-04', $calendar->startsOn($workspace, '2026-11')->toDateString());
        $this->assertSame(12345, $workspace->budgets()->where('month', '2026-11')->first()->reserve_cents);
        $this->assertSame('2026-10-05', $calendar->period($workspace, '2026-09')['end']->toDateString());
    }

    public function test_cycle_permissions_are_enforced_on_every_action(): void
    {
        $workspace = $this->workspace();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->assertDontSee('Alterar ciclo financeiro')->call('startEditingCycle')->assertForbidden();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->set('cycleStartInput', '2026-10-05')->call('saveCycle')->assertForbidden();
        $workspace->members()->updateExistingPivot($reader, ['permissions' => ['finance.view', 'budget.manage']]);
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])->call('startEditingCycle');
        $workspace->members()->updateExistingPivot($reader, ['permissions' => ['finance.view']]);
        $component->set('cycleStartInput', '2026-10-05')->call('saveCycle')->assertForbidden();
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_default_navigation_and_saved_entry_redirect_to_the_actual_cycle(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $workspace = $this->workspace();
        $this->configure($workspace);
        $this->get(route('finance.index', $workspace))->assertSee('Outubro de 2026');
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Compra')->set('form.amount', '12.34')->call('save')
            ->assertRedirect(route('finance.index', [$workspace, 'month' => '2026-10']));
        $this->post(route('entries.store', $workspace), ['description' => 'Outra', 'amount' => '5.00', 'type' => 'expense', 'date' => '2026-11-02'])
            ->assertRedirect(route('finance.index', [$workspace, 'month' => '2026-10']));
        $this->assertDatabaseMissing('financial_months', ['workspace_id' => $workspace->id, 'month' => '2026-10']);
        $this->assertDatabaseMissing('financial_months', ['workspace_id' => $workspace->id, 'month' => '2026-11']);
    }

    public function test_sync_uses_previous_cycle_and_selected_start_without_changing_source_dates(): void
    {
        $workspace = $this->workspace();
        $this->configure($workspace);
        $this->entry($workspace, '2026-10-05', 10000, ['is_fixed' => true, 'type' => 'income']);
        $this->entry($workspace, '2026-11-02', 2000, ['is_fixed' => true]);
        $this->entry($workspace, '2026-10-04', 5000, ['is_fixed' => true]);
        $opener = app(OpenFinancialMonth::class);
        $this->assertSame(2, $opener->handle($workspace, '2026-11', '2026-11-03', '2026-11-30'));
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'date' => '2026-11-03 00:00:00', 'amount_cents' => 10000, 'paid' => true]);
        // All copied fixed entries use the selected cycle start.
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'date' => '2026-11-03 00:00:00', 'amount_cents' => 2000, 'paid' => true]);
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'date' => '2026-11-02 00:00:00', 'amount_cents' => 2000, 'paid' => true]);
        $this->assertCount(2, app(FinancialSummary::class)->forMonth($workspace, '2026-11')['entries']);
    }

    public function test_adjusted_limit_uses_cycle_dates_instead_of_calendar_month(): void
    {
        $workspace = $this->workspace();
        $this->configure($workspace);
        foreach (['2026-10-04' => 4, '2026-10-05' => 4, '2026-10-12' => 3, '2026-10-19' => 2, '2026-10-26' => 1, '2026-11-02' => 1, '2026-11-03' => 0] as $date => $remaining) {
            $this->travelTo(CarbonImmutable::parse($date.' 12:00:00'));
            $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
            $this->assertSame($remaining, $summary['weeklyPlan']['remainingWeeks'], $date);
        }
    }
}
