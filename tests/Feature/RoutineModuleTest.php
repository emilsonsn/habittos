<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Livewire\RoutineBoard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\RoutineCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RoutineModuleTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'America/Fortaleza'));
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Pessoal');
    }

    private function board(Workspace $workspace)
    {
        return Livewire::test(RoutineBoard::class, ['workspaceId' => $workspace->id]);
    }

    public function test_routine_has_fixed_weekday_columns_and_current_week_navigation(): void
    {
        $workspace = $this->workspace();
        $this->get(route('routine.index', $workspace))->assertOk()->assertSee('Minha rotina')
            ->assertSeeInOrder(['Rotina diária', 'Financeiro'])
            ->assertSeeInOrder(array_map(fn ($day) => $day->label(), Weekday::cases()))
            ->assertSee('Semana 45 de 2026')->assertSee('02/11/2026 a 08/11/2026');
        $this->board($workspace)->set('titles.1', 'Rascunho')->call('navigateWeek', -1)
            ->assertSet('week', 44)->assertSet('titles', [])->assertSee('apenas consulta')
            ->call('navigateWeek', 1)->assertSet('week', 45)
            ->call('navigateWeek', 1)->assertSet('week', 46)->assertSee('ainda não começou')
            ->call('openCurrentWeek')->assertSet('week', 45);
        $this->get(route('routine.index', [$workspace, 'year' => 2026, 'week' => 44]))->assertOk()->assertSee('Semana 44 de 2026');
    }

    public function test_tasks_and_checks_persist_on_the_correct_day_and_can_be_unchecked(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.1', '  Caminhar 20 minutos  ')->call('addTask', 1)->assertHasNoErrors();
        $activity = $workspace->routineActivities()->firstOrFail();
        $this->assertSame('Caminhar 20 minutos', $activity->title);
        $this->assertSame(Weekday::Monday, $activity->weekday);
        $this->assertSame('2026-11-02', $activity->week_start->toDateString());
        $component->call('setCompleted', $activity->id, true)->assertHasNoErrors()->assertSee('1 de 1 concluídas');
        $this->assertNotNull($activity->fresh()->completed_at);
        $this->board($workspace)->assertSee('Caminhar 20 minutos')->assertSee('1 de 1 concluídas');
        $component->call('setCompleted', $activity->id, false)->assertHasNoErrors();
        $this->assertNull($activity->fresh()->completed_at);
        $this->assertDatabaseCount('routine_tasks', 1);
        $this->assertDatabaseCount('routine_activities', 1);
    }

    public function test_synchronization_copies_previous_week_with_fresh_checks_without_erasing_history(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.3', 'Ler')->call('addTask', 3);
        $original = $workspace->routineActivities()->firstOrFail();
        $component->call('setCompleted', $original->id, true);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 00:00:00', 'America/Fortaleza'));
        $this->board($workspace)->assertSet('week', 46)->assertDontSee('Ler')->assertSee('Sincronizar semana anterior')
            ->call('synchronizePreviousWeek')->assertHasNoErrors()->assertSee('Ler')->assertSee('0 de 1 concluídas')
            ->assertDontSee('data-confirm-title="Sincronizar semana anterior?"', false);
        $this->board($workspace)->call('synchronizePreviousWeek')->assertHasErrors('synchronization');
        $next = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->firstOrFail();
        $this->assertNull($next->completed_at);
        $this->assertNotNull($original->fresh()->completed_at);
        $this->board($workspace)->call('navigateWeek', -1)->assertSee('1 de 1 concluídas')->assertSet('week', 45);
        $this->board($workspace);
        $this->assertDatabaseCount('routine_activities', 2);
    }

    public function test_history_is_not_generated_on_read_and_removal_preserves_previous_weeks(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.7', 'Organizar a semana')->call('addTask', 7);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $component = $this->board($workspace)->call('synchronizePreviousWeek')->assertHasNoErrors();
        $current = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->firstOrFail();
        $component->call('removeTask', $current->id)->assertHasNoErrors()->assertSee('0 de 0 concluídas');
        $component->call('navigateWeek', -1)->assertSee('Organizar a semana');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00:00', 'America/Fortaleza'));
        $this->board($workspace)->call('navigateWeek', -1)->assertDontSee('Organizar a semana')->assertSee('0 de 0 concluídas');
        $this->assertDatabaseCount('routine_activities', 1);
    }

    public function test_future_empty_weeks_can_be_synchronized_and_show_saved_unchecked_activities(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.1', 'Caminhar')->call('addTask', 1);
        $source = $workspace->routineActivities()->firstOrFail();
        $component->call('setCompleted', $source->id, true);
        $future = $this->board($workspace)->call('navigateWeek', 1)->assertSee('Sincronizar semana anterior')
            ->call('synchronizePreviousWeek')->assertHasNoErrors()->assertSee('Caminhar')->assertSee('0 de 1 concluídas')
            ->assertDontSee('data-confirm-title="Sincronizar semana anterior?"', false);
        $future->call('synchronizePreviousWeek')->assertHasErrors('synchronization');
        $copy = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->firstOrFail();
        $this->assertNull($copy->completed_at);
        $this->assertNotNull($source->fresh()->completed_at);
        $this->board($workspace)->call('navigateWeek', 1)->assertSee('Caminhar')->assertSee('0 de 1 concluídas')
            ->call('setCompleted', $copy->id, true)->assertForbidden();
        $this->board($workspace)->call('navigateWeek', 1)->call('navigateWeek', 1)
            ->call('synchronizePreviousWeek')->assertHasNoErrors()->assertSee('Caminhar');
        $this->assertDatabaseCount('routine_activities', 3);
    }

    public function test_synchronization_requires_an_empty_week_and_never_overwrites_checks(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.1', 'Anterior')->call('addTask', 1);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $stale = $this->board($workspace)->assertSee('Sincronizar semana anterior');
        $component = $this->board($workspace)->set('titles.2', 'Atual')->call('addTask', 2);
        $current = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->firstOrFail();
        $component->call('setCompleted', $current->id, true);
        $stale->call('synchronizePreviousWeek')->assertHasErrors('synchronization');
        $this->assertNotNull($current->fresh()->completed_at);
        $this->assertDatabaseCount('routine_activities', 2);
    }

    public function test_synchronization_uses_only_the_immediately_previous_week_of_this_workspace(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.1', 'Antiga')->call('addTask', 1);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->board($other)->set('titles.1', 'Outro ambiente')->call('addTask', 1);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 10:00:00', 'America/Fortaleza'));
        $this->board($workspace)->call('synchronizePreviousWeek')->assertHasErrors('synchronization')
            ->assertSee('A semana anterior não possui atividades');
        $this->assertDatabaseCount('routine_activities', 2);
        $this->assertDatabaseCount('routine_tasks', 2);
    }

    public function test_synchronization_preserves_days_order_and_handles_iso_year_boundary(): void
    {
        $workspace = $this->workspace();
        $this->travelTo(CarbonImmutable::parse('2020-12-28 10:00:00', 'America/Fortaleza'));
        $component = $this->board($workspace)->set('titles.7', 'Domingo')->call('addTask', 7)
            ->set('titles.1', 'Primeira')->call('addTask', 1)->set('titles.1', 'Segunda')->call('addTask', 1);
        $source = $workspace->routineActivities()->orderBy('id')->get();
        $component->call('setCompleted', $source->first()->id, true);
        $this->travelTo(CarbonImmutable::parse('2021-01-04 10:00:00', 'America/Fortaleza'));
        $this->board($workspace)->assertSet('year', 2021)->assertSet('week', 1)
            ->call('synchronizePreviousWeek')->assertHasNoErrors()->assertSee('0 de 3 concluídas');
        $copies = $workspace->routineActivities()->whereDate('week_start', '2021-01-04')->orderBy('id')->get();
        $this->assertSame($source->pluck('title')->all(), $copies->pluck('title')->all());
        $this->assertSame($source->pluck('weekday')->all(), $copies->pluck('weekday')->all());
        $this->assertTrue($copies->every(fn ($copy) => $copy->completed_at === null));
        $this->assertNotNull($source->first()->fresh()->completed_at);
    }

    public function test_historical_and_future_weeks_cannot_be_changed(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.1', 'Alongar')->call('addTask', 1);
        $activity = $workspace->routineActivities()->firstOrFail();
        foreach ([-1, 1] as $direction) {
            foreach (['addTask' => [1], 'setCompleted' => [$activity->id, true], 'removeTask' => [$activity->id]] as $action => $args) {
                $this->board($workspace)->call('navigateWeek', $direction)->set('titles.1', 'Inválida')
                    ->call($action, ...$args)->assertForbidden();
            }
        }
        $this->board($workspace)->call('navigateWeek', -1)->assertDontSee('data-confirm-title="Sincronizar semana anterior?"', false)
            ->call('synchronizePreviousWeek')->assertForbidden();
        $this->assertDatabaseCount('routine_activities', 1);
        $this->assertNull($activity->fresh()->completed_at);
    }

    public function test_activity_ids_are_scoped_to_workspace_and_selected_week(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $this->board($other)->set('titles.1', 'Privada')->call('addTask', 1);
        $foreign = $other->routineActivities()->firstOrFail();
        foreach (['setCompleted' => [$foreign->id, true], 'removeTask' => [$foreign->id]] as $action => $args) {
            try {
                $this->board($workspace)->call($action, ...$args);
                $this->fail('Não deve acessar atividade de outro ambiente.');
            } catch (ModelNotFoundException) {
                $this->assertNull($foreign->fresh()->completed_at);
            }
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $this->expectException(ModelNotFoundException::class);
        $this->board($other)->call('setCompleted', $foreign->id, true);
    }

    public function test_routine_permissions_are_rechecked_and_do_not_grant_other_modules(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.1', 'Ler')->call('addTask', 1);
        $activity = $workspace->routineActivities()->firstOrFail();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['routine.view']]);
        $this->actingAs($reader)->get(route('routine.index', $workspace))->assertOk()->assertSee('Ler')->assertDontSee('Adicionar atividade…');
        foreach (['addTask' => [1], 'setCompleted' => [$activity->id, true], 'removeTask' => [$activity->id], 'synchronizePreviousWeek' => []] as $action => $args) {
            $this->board($workspace)->call($action, ...$args)->assertForbidden();
        }
        $this->get(route('goals.index', $workspace))->assertForbidden();
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['routine.view', 'routine.manage']]);
        $component = $this->board($workspace);
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['routine.view']]);
        $component->call('synchronizePreviousWeek')->assertForbidden();
        $outsider = User::factory()->create();
        app(CreateWorkspace::class)->handle($outsider, 'Privado');
        $this->actingAs($outsider)->get(route('routine.index', $workspace))->assertNotFound();
    }

    public function test_task_validation_and_future_reads_do_not_create_invalid_records(): void
    {
        $workspace = $this->workspace();
        $this->board($workspace)->set('titles.1', '  ')->call('addTask', 1)->assertHasErrors('titles.1')
            ->set('titles.1', str_repeat('a', 201))->call('addTask', 1)->assertHasErrors('titles.1')
            ->set('titles.8', 'Inválida')->call('addTask', 8)->assertHasErrors('titles.8');
        $this->board($workspace)->call('navigateWeek', 1);
        $this->assertDatabaseCount('routine_tasks', 0);
        $this->assertDatabaseCount('routine_activities', 0);
    }

    public function test_iso_week_navigation_handles_year_boundaries_and_invalid_week_53(): void
    {
        $workspace = $this->workspace();
        $this->travelTo(CarbonImmutable::parse('2021-01-01 10:00:00', 'America/Fortaleza'));
        $this->board($workspace)->assertSet('year', 2020)->assertSet('week', 53)
            ->call('navigateWeek', 1)->assertSet('year', 2021)->assertSet('week', 1)
            ->call('navigateWeek', -1)->assertSet('year', 2020)->assertSet('week', 53);
        $this->expectException(ValidationException::class);
        app(RoutineCalendar::class)->start(2021, 53);
    }

    public function test_week_rollover_uses_fortaleza_timezone(): void
    {
        $workspace = $this->workspace();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 02:59:59', 'UTC'));
        $this->board($workspace)->assertSet('week', 45);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 03:00:00', 'UTC'));
        $this->board($workspace)->assertSet('week', 46);
    }

    public function test_editing_title_preserves_day_check_and_other_weeks(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.2', 'Ler')->call('addTask', 2);
        $original = $workspace->routineActivities()->firstOrFail();
        $component->call('setCompleted', $original->id, true);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $component = $this->board($workspace)->call('synchronizePreviousWeek');
        $current = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->firstOrFail();
        $component->call('setCompleted', $current->id, true);
        $completedAt = $current->fresh()->completed_at;
        $component->call('editTask', $current->id)->assertSet('editingTitle', 'Ler')
            ->assertSee('wire:submit="saveTask"', false)->set('editingTitle', '  Ler 20 páginas  ')
            ->call('saveTask')->assertHasNoErrors()->assertSet('editingId', null)->assertSee('Ler 20 páginas');
        $this->assertSame('Ler 20 páginas', $current->fresh()->title);
        $this->assertSame(Weekday::Tuesday, $current->fresh()->weekday);
        $this->assertTrue($completedAt->equalTo($current->fresh()->completed_at));
        $this->assertSame('Ler 20 páginas', $workspace->routineTasks()->findOrFail($current->routine_task_id)->title);
        $this->assertSame('Ler', $original->fresh()->title);
        $this->board($workspace)->call('navigateWeek', -1)->assertSee('Ler')->assertDontSee('Ler 20 páginas');
    }

    public function test_editing_validates_title_and_can_be_cancelled_or_reset_on_navigation(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.1', 'Caminhar')->call('addTask', 1);
        $activity = $workspace->routineActivities()->firstOrFail();
        $component->call('editTask', $activity->id)->set('editingTitle', '   ')->call('saveTask')->assertHasErrors('editingTitle')
            ->set('editingTitle', str_repeat('a', 201))->call('saveTask')->assertHasErrors('editingTitle')
            ->call('cancelEditing')->assertSet('editingId', null)->assertHasNoErrors('editingTitle');
        $this->assertSame('Caminhar', $activity->fresh()->title);
        $component->call('editTask', $activity->id)->set('editingTitle', 'Rascunho')->call('navigateWeek', 1)
            ->assertSet('editingId', null)->assertSet('editingTitle', '');
        $this->assertSame('Caminhar', $activity->fresh()->title);
    }

    public function test_editing_rechecks_permissions_and_rejects_other_workspaces_and_weeks(): void
    {
        $workspace = $this->workspace();
        $component = $this->board($workspace)->set('titles.1', 'Ler')->call('addTask', 1);
        $activity = $workspace->routineActivities()->firstOrFail();
        foreach ([-1, 1] as $direction) {
            $this->board($workspace)->call('navigateWeek', $direction)->call('editTask', $activity->id)->assertForbidden();
            $this->board($workspace)->call('editTask', $activity->id)->set('week', 45 + $direction)
                ->set('editingTitle', 'Inválida')->call('saveTask')->assertForbidden();
        }
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        try {
            $this->board($other)->call('editTask', $activity->id);
            $this->fail('Não deve editar atividade de outro ambiente.');
        } catch (ModelNotFoundException) {
            $this->assertSame('Ler', $activity->fresh()->title);
        }
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['routine.view', 'routine.manage']]);
        $this->actingAs($reader);
        $component = $this->board($workspace)->call('editTask', $activity->id)->set('editingTitle', 'Inválida');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['routine.view']]);
        $component->call('saveTask')->assertForbidden();
        $this->board($workspace)->assertDontSee('title="Editar atividade"', false)->call('editTask', $activity->id)->assertForbidden();
        $this->assertSame('Ler', $activity->fresh()->title);
    }

    public function test_reordering_persists_per_day_and_is_copied_to_the_next_week(): void
    {
        $workspace = $this->workspace();
        $board = $this->board($workspace);
        foreach (['Primeira', 'Segunda', 'Terceira'] as $title) {
            $board->set('titles.1', $title)->call('addTask', 1);
        }
        $board->set('titles.2', 'Outro dia')->call('addTask', 2);
        $items = $workspace->routineActivities()->where('weekday', 1)->orderBy('id')->get();
        $board->call('setCompleted', $items[2]->id, true)
            ->call('reorderTask', $items[2]->id, 0)->assertHasNoErrors()
            ->assertSeeInOrder(['Primeira', 'Segunda']);
        $this->assertSame([$items[2]->id, $items[0]->id, $items[1]->id], $workspace->routineActivities()
            ->where('weekday', 1)->orderBy('position')->pluck('id')->all());
        $board->call('reorderTask', $items[0]->id, 2)->assertHasNoErrors();
        $this->board($workspace)->assertSeeInOrder(['Terceira', 'Segunda', 'Primeira', 'Outro dia']);
        $this->assertNotNull($items[2]->fresh()->completed_at);
        $this->assertSame(Weekday::Monday, $items[2]->fresh()->weekday);
        $board->set('titles.1', 'Adicionada depois')->call('addTask', 1);
        $this->board($workspace)->assertSeeInOrder(['Terceira', 'Segunda', 'Primeira', 'Adicionada depois']);
        $source = $workspace->routineActivities()->where('weekday', 1)->orderBy('position')->get();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'America/Fortaleza'));
        $this->board($workspace)->call('synchronizePreviousWeek')->assertHasNoErrors()
            ->assertSeeInOrder(['Terceira', 'Segunda', 'Primeira', 'Adicionada depois']);
        $copies = $workspace->routineActivities()->whereDate('week_start', '2026-11-09')->where('weekday', 1)->orderBy('position')->get();
        $this->assertSame($source->pluck('title')->all(), $copies->pluck('title')->all());
        $this->assertTrue($copies->every(fn ($activity) => $activity->completed_at === null));
        $this->assertSame($source->pluck('position')->all(), $source->map(fn ($activity) => $activity->fresh()->position)->all());
        $this->assertNotNull($items[2]->fresh()->completed_at);
    }

    public function test_reordering_validates_position_and_rechecks_week_workspace_and_permissions(): void
    {
        $workspace = $this->workspace();
        $board = $this->board($workspace)->set('titles.1', 'Atividade')->call('addTask', 1);
        $activity = $workspace->routineActivities()->firstOrFail();
        foreach ([-1, 1] as $position) {
            $board->call('reorderTask', $activity->id, $position)->assertHasErrors('position');
        }
        foreach ([-1, 1] as $direction) {
            $this->board($workspace)->call('navigateWeek', $direction)->call('reorderTask', $activity->id, 0)->assertForbidden();
        }
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        try {
            $this->board($other)->call('reorderTask', $activity->id, 0);
            $this->fail('Não deve reordenar atividade de outro ambiente.');
        } catch (ModelNotFoundException) {
            $this->assertSame(1, $activity->fresh()->position);
        }
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['routine.view', 'routine.manage']]);
        $this->actingAs($reader);
        $board = $this->board($workspace);
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['routine.view']]);
        $board->call('reorderTask', $activity->id, 0)->assertForbidden();
        $this->board($workspace)->assertDontSee('wire:sort="reorderTask"', false);
    }

    public function test_switching_workspaces_keeps_the_routine_module(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        foreach (['routine', 'routine.index'] as $module) {
            $this->post(route('workspaces.select'), ['workspace_id' => $other->id, 'module' => $module])
                ->assertRedirect(route('routine.index', $other));
        }
        $this->assertNotSame($workspace->id, $other->id);
    }
}
