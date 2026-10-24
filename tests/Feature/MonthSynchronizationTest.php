<?php

namespace Tests\Feature;

use App\Livewire\FinanceDashboard;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\FinancialCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MonthSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return app(CreateWorkspace::class)->handle($owner, 'Casa');
    }

    private function fixed(Workspace $workspace, string $type, string $date = '2026-10-10'): void
    {
        $workspace->entries()->create(['description' => 'Fixo '.$type, 'type' => $type, 'amount_cents' => 12345,
            'date' => $date, 'paid' => true, 'is_fixed' => true, 'notes' => 'Detalhes',
            'category_id' => $workspace->categories()->first()->id]);
    }

    public function test_confirmation_copies_only_previous_fixed_income_and_expenses_using_selected_start_and_saves_both_boundaries(): void
    {
        $workspace = $this->workspace();
        foreach (['income', 'expense', 'investment'] as $type) {
            $this->fixed($workspace, $type);
        }
        $workspace->entries()->create(['description' => 'Variável', 'type' => 'expense', 'amount_cents' => 500, 'date' => '2026-10-15']);
        $this->fixed($workspace, 'expense', '2026-09-10');
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->fixed($other, 'expense');
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11', 'section' => 'fixed-income'])
            ->assertSee('wire:click="startSynchronization"', false)
            ->call('startSynchronization')->assertDispatched('open-month-sync')
            ->assertSet('cycleStartInput', '2026-11-01')->assertSet('cycleEndInput', '2026-11-30');
        $this->assertSame(5, $workspace->entries()->count());
        $component->set('cycleStartInput', '2026-11-05')->set('cycleEndInput', '2026-12-03')
            ->call('synchronizePreviousMonth')->assertHasNoErrors()->assertDispatched('close-month-sync')
            ->assertSet('section', 'fixed-income')->assertSee('05/11/2026 a 03/12/2026')
            ->assertDontSee('wire:click="startSynchronization"', false);
        $copies = $workspace->entries()->whereDate('date', '2026-11-05')->get();
        $this->assertCount(2, $copies);
        $this->assertSame(['expense', 'income'], $copies->pluck('type')->sort()->values()->all());
        foreach ($copies as $entry) {
            $this->assertFalse($entry->paid);
            $this->assertTrue($entry->is_fixed);
            $this->assertSame(12345, $entry->amount_cents);
            $this->assertSame('Detalhes', $entry->notes);
            $this->assertSame($workspace->categories()->first()->id, $entry->category_id);
        }
        $calendar = app(FinancialCalendar::class);
        $this->assertSame('2026-11-05', $calendar->period($workspace, '2026-11')['start']->toDateString());
        $this->assertSame('2026-12-04', $calendar->period($workspace, '2026-11')['end']->toDateString());
        $this->assertSame('2026-12-04', $calendar->startsOn($workspace, '2026-12')->toDateString());
        $this->assertSame('2026-11', $calendar->monthForDate($workspace, '2026-12-03'));
        $this->assertSame('2026-12', $calendar->monthForDate($workspace, '2026-12-04'));
        $component->call('startSynchronization')->assertHasErrors('synchronization');
        $this->assertSame(7, $workspace->entries()->count());
    }

    public function test_any_existing_entry_hides_the_button_even_when_the_current_section_has_no_records(): void
    {
        $workspace = $this->workspace();
        foreach (['income', 'expense', 'investment'] as $type) {
            $entry = $workspace->entries()->create(['description' => 'Já existe', 'type' => $type, 'amount_cents' => 100, 'date' => '2026-11-10']);
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11', 'section' => 'fixed-income'])
                ->assertDontSee('wire:click="startSynchronization"', false)
                ->call('startSynchronization')->assertHasErrors('synchronization');
            $this->post(route('finance.open-month', $workspace), ['month' => '2026-11', 'cycleStartInput' => '2026-11-15', 'cycleEndInput' => '2026-11-30'])
                ->assertSessionHasErrors('synchronization');
            $this->assertDatabaseCount('budgets', 0);
            $entry->delete();
        }
    }

    public function test_confirmation_rechecks_empty_month_after_another_entry_is_created(): void
    {
        $workspace = $this->workspace();
        $this->fixed($workspace, 'expense');
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('startSynchronization')->set('cycleStartInput', '2026-11-15');
        $workspace->entries()->create(['description' => 'Outro usuário', 'type' => 'income', 'amount_cents' => 100, 'date' => '2026-11-02']);
        $component->call('synchronizePreviousMonth')->assertHasErrors('synchronization');
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_invalid_dates_and_cancellation_do_not_save_any_data(): void
    {
        $workspace = $this->workspace();
        $this->fixed($workspace, 'expense');
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('startSynchronization')->set('cycleStartInput', '2026-10-10')
            ->call('synchronizePreviousMonth')->assertHasErrors('cycleStartInput')
            ->set('cycleStartInput', '2026-11-05')->set('cycleEndInput', '2026-11-04')
            ->call('synchronizePreviousMonth')->assertHasErrors('cycleEndInput')
            ->call('cancelSynchronization')->assertSet('synchronizingMonth', null);
        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseCount('budgets', 0);
        $component->call('synchronizePreviousMonth')->assertStatus(422);
    }

    public function test_browsing_switching_workspace_and_creating_first_entry_do_not_copy_fixed_records(): void
    {
        $workspace = $this->workspace();
        $this->fixed($workspace, 'expense');
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('openMonth', '2026-11')->assertSee('wire:click="startSynchronization"', false)
            ->call('startSynchronization')->call('openMonth', '2026-12')->assertSet('synchronizingMonth', null);
        Livewire::test(WorkspaceSwitcher::class, ['activeId' => $workspace->id, 'month' => '2026-11'])
            ->call('selectWorkspace', $workspace->id)->assertRedirect();
        $this->assertDatabaseCount('entries', 1);
        $this->post(route('entries.store', $workspace), ['description' => 'Manual', 'amount' => '10.00', 'date' => '2026-11-05'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('financial_months', 0);
    }

    public function test_empty_month_with_old_initialization_marker_can_be_synchronized_and_missing_source_is_reported(): void
    {
        $workspace = $this->workspace();
        DB::table('financial_months')->insert(['workspace_id' => $workspace->id, 'month' => '2026-11', 'created_at' => now(), 'updated_at' => now()]);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('startSynchronization')->call('synchronizePreviousMonth')->assertHasNoErrors()
            ->assertSee('O mês anterior não possui receitas ou gastos fixos');
        $this->fixed($workspace, 'income');
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->call('startSynchronization')->call('synchronizePreviousMonth')->assertHasNoErrors()
            ->assertDontSee('wire:click="startSynchronization"', false);
        $this->assertDatabaseCount('entries', 2);
    }

    public function test_sync_requires_create_and_budget_permissions_and_rechecks_them_at_confirmation(): void
    {
        $workspace = $this->workspace();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])
            ->assertDontSee('wire:click="startSynchronization"', false)->call('startSynchronization')->assertForbidden();
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view', 'finance.create', 'budget.manage']]);
        $component = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11'])->call('startSynchronization');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view', 'finance.create']]);
        $component->call('synchronizePreviousMonth')->assertForbidden();
        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('budgets', 0);
    }
}
