<?php

namespace Tests\Feature;

use App\Enums\GoalSection;
use App\Livewire\GoalsBoard;
use App\Livewire\GoalsDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\ManageGoals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalsPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Pessoal');
    }

    private function goal(Workspace $workspace, array $extra = [])
    {
        return app(ManageGoals::class)->save($workspace, $extra + [
            'title' => 'Correr 5 km', 'period' => 'annual', 'year' => 2026, 'priority' => 'high',
            'category' => 'Saúde', 'status' => 'in_progress', 'progress_percent' => '40.25',
            'due_date' => '2026-12-31',
        ]);
    }

    public function test_four_pages_have_their_own_navigation_and_period_records(): void
    {
        $workspace = $this->workspace();
        $annual = $this->goal($workspace);
        $monthly = $this->goal($workspace, ['title' => 'Ler em novembro', 'period' => 'monthly', 'month' => 11]);
        $weekly = $this->goal($workspace, ['title' => 'Treinar na semana', 'period' => 'weekly', 'week' => 44]);
        foreach (GoalSection::cases() as $section) {
            $response = $this->get(route($section->routeName(), [$workspace, 'year' => 2026, 'month' => 11, 'week' => 44]))->assertOk()->assertSee($section->label());
            foreach (GoalSection::cases() as $item) {
                $response->assertSee(route($item->routeName(), $workspace), false);
            }
            if ($section !== GoalSection::Dashboard) {
                $response->assertSee(match ($section) {
                    GoalSection::Annual => $annual->title, GoalSection::Monthly => $monthly->title, default => $weekly->title
                });
            }
        }
        $this->get(route('goals.annual', [$workspace, 'year' => 2026]))->assertDontSee('Ler em novembro')->assertDontSee('Treinar na semana')->assertDontSee('R$');
        $this->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'goals.weekly'])->assertRedirect(route('goals.weekly', $workspace));
    }

    public function test_dialog_does_not_persist_until_save_and_preserves_all_notion_fields(): void
    {
        $workspace = $this->workspace();
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->call('openCreate')->set('form.title', 'Descartada')->call('closeForm')->assertSet('formOpen', false);
        $this->assertDatabaseCount('goals', 0);
        $component->call('openCreate')->set('form.title', 'Aprender inglês')->set('form.category', 'Profissional')
            ->set('form.progress_percent', 70)->set('form.status', 'completed')->set('form.priority', 'low')
            ->set('form.due_date', '2027-03-31')->set('form.year', 2026)
            ->call('saveGoal')->assertHasNoErrors()->assertSet('formOpen', false)->assertSee('Aprender inglês');
        $goal = $workspace->goals()->firstOrFail();
        $this->assertSame('70.00', $goal->progress_percent);
        $this->assertSame('completed', $goal->status);
        $this->assertSame('2027-03-31', $goal->due_date->toDateString());
        $component->call('openEdit', $goal->id)->set('form.title', 'Aprender inglês melhor')->call('saveGoal')->assertHasNoErrors();
        $this->assertSame('Aprender inglês melhor', $goal->fresh()->title);
        $this->assertSame(0, $workspace->entries()->count());
    }

    public function test_table_and_kanban_share_filters_and_status_changes_preserve_progress(): void
    {
        $workspace = $this->workspace();
        $goal = $this->goal($workspace);
        $this->goal($workspace, ['title' => 'Ler livro', 'category' => 'Profissional', 'priority' => 'low', 'status' => 'completed']);
        $this->goal($workspace, ['title' => 'Outro ano', 'year' => 2025]);
        $this->goal($workspace, ['title' => 'Meta mensal', 'period' => 'monthly', 'month' => 10]);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->goal($other, ['title' => 'Meta privada']);
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->set('yearFilter', 2026)->assertViewHas('total', 2)->assertDontSee('Meta privada')
            ->set('search', 'Correr')->set('statusFilter', 'in_progress')->set('priorityFilter', 'high')
            ->set('categoryFilter', 'Saúde')
            ->set('dateTo', '2026-12-31')->assertViewHas('total', 1)
            ->call('selectDisplay', 'kanban')->assertViewHas('total', 1)->assertSee('Kanban de metas por status')
            ->call('changeStatus', $goal->id, 'completed')->assertViewHas('total', 0)
            ->call('clearFilters')->assertViewHas('total', 2)->assertSee('Correr 5 km')
            ->call('selectDisplay', 'table')->assertViewHas('total', 2);
        $this->assertSame('40.25', $goal->fresh()->progress_percent);
        $component->set('dateTo', '')->set('dateFrom', '2027-01-01')->assertViewHas('total', 0);
    }

    public function test_invalid_deadline_range_shows_an_error_and_recovers_when_cleared(): void
    {
        $workspace = $this->workspace();
        $this->goal($workspace);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->set('yearFilter', 2026)
            ->set('dateFrom', '2027-01-01')->set('dateTo', '2026-12-31')
            ->assertHasErrors('dateTo')->assertViewHas('total', 0)
            ->call('clearFilters')->assertHasNoErrors()->assertViewHas('total', 1);
    }

    public function test_table_and_kanban_order_each_period_by_priority_before_deadline(): void
    {
        $workspace = $this->workspace();
        foreach (['annual', 'monthly', 'weekly'] as $period) {
            $context = ['period' => $period, 'month' => 11, 'week' => 45];
            $low = $this->goal($workspace, $context + ['priority' => 'low', 'due_date' => '2026-01-01']);
            $medium = $this->goal($workspace, $context + ['priority' => 'medium', 'due_date' => '2026-02-01']);
            $highWithoutDeadline = $this->goal($workspace, $context + ['priority' => 'high', 'due_date' => null]);
            $high = $this->goal($workspace, $context + ['priority' => 'high', 'due_date' => '2026-12-31']);
            $expected = [$high->id, $highWithoutDeadline->id, $medium->id, $low->id];
            Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id, 'period' => $period])
                ->set('yearFilter', 2026)->set('monthFilter', '11')->set('weekFilter', '45')
                ->assertViewHas('goals', fn ($goals) => $goals->pluck('id')->all() === $expected)
                ->call('selectDisplay', 'kanban')
                ->assertViewHas('goals', fn ($goals) => $goals->pluck('id')->all() === $expected);
        }
    }

    public function test_not_started_is_the_default_and_can_be_filtered_moved_and_counted(): void
    {
        $workspace = $this->workspace();
        $existing = $this->goal($workspace, ['status' => 'not_done']);
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->set('yearFilter', 2026)->call('openCreate')->assertSet('form.status', 'not_started')
            ->set('form.title', 'Aprender algo novo')->call('saveGoal')->assertHasNoErrors()
            ->call('selectDisplay', 'kanban')
            ->set('statusFilter', 'not_started')->assertViewHas('total', 1);
        $this->get(route('goals.annual', [$workspace, 'year' => 2026, 'view' => 'kanban']))
            ->assertOk()->assertSeeInOrder(['data-goal-status="not_started"', 'data-goal-status="in_progress"', 'data-goal-status="completed"', 'data-goal-status="not_done"'], false);
        $goal = $workspace->goals()->where('title', 'Aprender algo novo')->firstOrFail();
        $this->assertSame('not_started', $goal->status);
        $component->call('changeStatus', $existing->id, 'not_started')->assertHasNoErrors()->assertViewHas('total', 2);
        $this->assertSame('40.25', $existing->fresh()->progress_percent);
        Livewire::test(GoalsDashboard::class, ['workspaceId' => $workspace->id])->set('year', 2026)
            ->assertViewHas('byStatus', fn ($items) => $items['not_started']['count'] === 2 && $items['not_done']['count'] === 0);
    }

    public function test_optional_hierarchy_is_scoped_acyclic_and_deleting_parent_preserves_children(): void
    {
        $workspace = $this->workspace();
        $annual = $this->goal($workspace);
        $monthly = $this->goal($workspace, ['period' => 'monthly', 'month' => 10, 'parent_id' => $annual->id]);
        $weekly = $this->goal($workspace, ['period' => 'weekly', 'week' => 44, 'parent_id' => $monthly->id]);
        $independent = $this->goal($workspace, ['period' => 'weekly', 'week' => 45]);
        $this->assertNull($independent->parent_id);
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id, 'period' => 'monthly'])
            ->set('yearFilter', 2026)->set('monthFilter', '10')->call('openEdit', $monthly->id)->set('form.parent_id', $weekly->id)->call('saveGoal')->assertHasErrors('form.parent_id')
            ->set('form.parent_id', $annual->id)->call('saveGoal')->assertHasNoErrors();
        $component->call('deleteGoal', $annual->id);
        $this->assertNull($monthly->fresh()->parent_id);
        $this->assertSame($monthly->id, $weekly->fresh()->parent_id);
        $this->assertDatabaseCount('goals', 3);
    }

    public function test_dialog_period_is_derived_from_page_and_cannot_be_changed_through_form_state(): void
    {
        $workspace = $this->workspace();
        foreach (['annual', 'monthly', 'weekly'] as $period) {
            Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id, 'period' => $period])
                ->call('openCreate')->assertDontSee('<label>Período', false)
                ->set('form.title', 'Meta '.$period)->set('form.period', 'annual')
                ->call('saveGoal')->assertHasNoErrors()->assertSet('formOpen', false);
            $this->assertDatabaseHas('goals', ['title' => 'Meta '.$period, 'period' => $period]);
        }
    }

    public function test_time_navigation_crosses_years_and_creation_uses_the_open_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'America/Fortaleza'));
        $workspace = $this->workspace();
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->set('yearFilter', 2025)->call('navigatePeriod', -1)->assertSet('yearFilter', 2024)
            ->call('openCurrentPeriod')->assertSet('yearFilter', 2026)
            ->call('openCreate')->assertDontSee('<label>Ano', false)->set('form.title', 'Meta anual atual')
            ->set('form.year', 2030)->call('saveGoal')->assertHasNoErrors();
        $this->assertDatabaseHas('goals', ['title' => 'Meta anual atual', 'year' => 2026]);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id, 'period' => 'monthly'])
            ->set('yearFilter', 2026)->set('monthFilter', '12')->call('navigatePeriod', 1)
            ->assertSet('yearFilter', 2027)->assertSet('monthFilter', '1')->call('navigatePeriod', -1)
            ->assertSet('yearFilter', 2026)->assertSet('monthFilter', '12')
            ->call('openCreate')->set('form.title', 'Meta de dezembro')->set('form.year', 2030)->set('form.month', 6)
            ->call('saveGoal')->assertHasNoErrors();
        $this->assertDatabaseHas('goals', ['title' => 'Meta de dezembro', 'year' => 2026, 'month' => 12]);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id, 'period' => 'weekly'])
            ->set('yearFilter', 2026)->set('weekFilter', '53')->call('navigatePeriod', 1)
            ->assertSet('yearFilter', 2027)->assertSet('weekFilter', '1')->call('navigatePeriod', -1)
            ->assertSet('yearFilter', 2026)->assertSet('weekFilter', '53')
            ->call('openCreate')->set('form.title', 'Treinar')->set('form.year', 2027)->set('form.week', 1)
            ->set('form.progress_percent', 101)->call('saveGoal')->assertHasErrors('form.progress_percent')
            ->set('form.progress_percent', 50)->call('saveGoal')->assertHasNoErrors();
        $this->assertDatabaseHas('goals', ['title' => 'Treinar', 'period' => 'weekly', 'week' => 53, 'year' => 2026]);
    }

    public function test_permissions_and_workspace_boundaries_are_rechecked_for_all_mutations(): void
    {
        $workspace = $this->workspace();
        $goal = $this->goal($workspace);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $foreign = $this->goal($other, ['title' => 'Privada']);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['goals.view']]);
        $this->actingAs($reader)->get(route('goals.annual', [$workspace, 'year' => 2026]))->assertOk()->assertDontSee('+ Nova meta')->assertDontSee('wire:click="openCreate', false);
        foreach (['openCreate' => [], 'openEdit' => [$goal->id], 'deleteGoal' => [$goal->id], 'changeStatus' => [$goal->id, 'completed']] as $action => $args) {
            Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->call($action, ...$args)->assertForbidden();
        }
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['goals.view', 'goals.manage']]);
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->call('openEdit', $goal->id);
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['goals.view']]);
        $component->set('form.title', 'Não permitido')->call('saveGoal')->assertForbidden();
        $this->assertSame('Correr 5 km', $goal->fresh()->title);
    }

    public function test_readers_can_open_details_without_editing(): void
    {
        $workspace = $this->workspace();
        $goal = $this->goal($workspace);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['goals.view']]);
        $this->actingAs($reader);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->call('openDetails', $goal->id)
            ->assertSet('form.title', 'Correr 5 km')->assertSee('Detalhes da meta')
            ->assertDontSee('Salvar alterações')->assertDontSee('Excluir meta')
            ->set('form.title', 'Alteração proibida')->call('saveGoal')->assertForbidden();
        $this->assertSame('Correr 5 km', $goal->fresh()->title);
    }

    public function test_foreign_goal_id_cannot_be_opened_in_the_current_workspace(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $foreign = $this->goal($other, ['title' => 'Privada']);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->call('openEdit', $foreign->id);
    }

    public function test_dashboard_aggregates_only_workspace_and_selected_year_without_financial_data(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $workspace = $this->workspace();
        $this->goal($workspace, ['progress_percent' => 40, 'due_date' => '2026-10-01']);
        $this->goal($workspace, ['title' => 'Conquista', 'period' => 'monthly', 'month' => 10, 'status' => 'completed', 'progress_percent' => 100]);
        $this->goal($workspace, ['title' => 'Futuro', 'year' => 2027]);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->goal($other);
        Livewire::test(GoalsDashboard::class, ['workspaceId' => $workspace->id])->set('year', 2026)
            ->assertViewHas('total', 2)->assertViewHas('completed', 1)->assertViewHas('inProgress', 1)->assertViewHas('average', 70.0)
            ->assertViewHas('byStatus', fn ($items) => $items->pluck('count')->all() === [0, 1, 1, 0])
            ->assertViewHas('byPriority', fn ($items) => $items->sum('total') === 2 && $items->sum(fn ($item) => $item['statuses']->sum('count')) === 2)
            ->assertViewHas('byCategory', fn ($items) => $items->sum('total') === 2 && $items->sum('completed') === 1)
            ->assertViewHas('overdue', fn ($items) => $items->count() === 1)->assertDontSee('R$')
            ->set('year', 2027)->assertViewHas('total', 1);
    }

    public function test_dashboard_handles_empty_years_and_links_to_the_correct_month_and_week(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $workspace = $this->workspace();
        $monthly = $this->goal($workspace, ['title' => 'Meta de novembro', 'period' => 'monthly', 'month' => 11, 'due_date' => '2026-11-20']);
        $weekly = $this->goal($workspace, ['title' => 'Meta da semana 53', 'period' => 'weekly', 'week' => 53, 'due_date' => '2026-12-31']);
        $component = Livewire::test(GoalsDashboard::class, ['workspaceId' => $workspace->id])->set('year', 2026);
        foreach ([$monthly, $weekly] as $goal) {
            $component->assertSee(route('goals.'.$goal->period, ['workspace' => $workspace, 'year' => 2026, 'month' => $goal->month, 'week' => $goal->week, 'search' => $goal->title]));
        }
        $component->set('year', 2030)->assertViewHas('total', 0)
            ->assertViewHas('byStatus', fn ($items) => $items->sum('count') === 0)
            ->assertViewHas('byCategory', fn ($items) => $items->isEmpty())
            ->assertSee('Nenhuma meta neste ano.')->assertDontSee('NaN')->assertDontSee('INF');
    }
}
