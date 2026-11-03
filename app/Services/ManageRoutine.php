<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\Weekday;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageRoutine
{
    private function authorize(Workspace $workspace, Permission $permission): void
    {
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize($permission->value, $workspace);
    }

    public function activities(Workspace $workspace, CarbonImmutable $week): Collection
    {
        $this->authorize($workspace, Permission::RoutineView);

        return $workspace->routineActivities()->whereDate('week_start', $week)->orderBy('id')->get();
    }

    public function synchronizePreviousWeek(Workspace $workspace, CarbonImmutable $week): int
    {
        $this->authorize($workspace, Permission::RoutineManage);
        abort_if($week->lt(app(RoutineCalendar::class)->currentWeek()), 403, 'Semanas anteriores são apenas para consulta.');

        return DB::transaction(function () use ($workspace, $week) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            if ($workspace->routineActivities()->whereDate('week_start', $week)->exists()) {
                throw ValidationException::withMessages(['synchronization' => 'A sincronização só está disponível para uma semana vazia.']);
            }
            $previous = $workspace->routineActivities()->whereDate('week_start', $week->subWeek())->orderBy('id')->get();
            if ($previous->isEmpty()) {
                throw ValidationException::withMessages(['synchronization' => 'A semana anterior não possui atividades para sincronizar.']);
            }
            foreach ($previous as $activity) {
                $data = ['weekday' => $activity->weekday, 'title' => $activity->title];
                $task = $workspace->routineTasks()->create($data + ['starts_on' => $week->toDateString()]);
                $workspace->routineActivities()->create($data + [
                    'routine_task_id' => $task->id,
                    'week_start' => $week->toDateString(),
                    'completed_at' => null,
                ]);
            }

            return $previous->count();
        });
    }

    private function authorizeCurrentWeek(Workspace $workspace, CarbonImmutable $week): void
    {
        $this->authorize($workspace, Permission::RoutineManage);
        abort_unless($week->isSameDay(app(RoutineCalendar::class)->currentWeek()), 403, 'Apenas a semana atual pode ser alterada.');
    }

    public function add(Workspace $workspace, CarbonImmutable $week, int $weekday, string $title): void
    {
        $this->authorizeCurrentWeek($workspace, $week);
        $data = validator(['weekday' => $weekday, 'title' => trim($title)], [
            'weekday' => ['required', Rule::enum(Weekday::class)], 'title' => ['required', 'string', 'max:200'],
        ])->validate();
        DB::transaction(function () use ($workspace, $week, $data) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $task = $workspace->routineTasks()->create($data + ['starts_on' => $week->toDateString()]);
            $workspace->routineActivities()->create($data + ['routine_task_id' => $task->id, 'week_start' => $week->toDateString()]);
        });
    }

    public function setCompleted(Workspace $workspace, CarbonImmutable $week, int $id, bool $completed): void
    {
        $this->authorizeCurrentWeek($workspace, $week);
        $workspace->routineActivities()->whereDate('week_start', $week)->findOrFail($id)
            ->update(['completed_at' => $completed ? now() : null]);
    }

    public function rename(Workspace $workspace, CarbonImmutable $week, int $id, string $title): void
    {
        $this->authorizeCurrentWeek($workspace, $week);
        $data = validator(['editingTitle' => trim($title)], [
            'editingTitle' => ['required', 'string', 'max:200'],
        ])->validate();
        DB::transaction(function () use ($workspace, $week, $id, $data) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $activity = $workspace->routineActivities()->whereDate('week_start', $week)->findOrFail($id);
            $activity->update(['title' => $data['editingTitle']]);
            $workspace->routineTasks()->findOrFail($activity->routine_task_id)->update(['title' => $data['editingTitle']]);
        });
    }

    public function remove(Workspace $workspace, CarbonImmutable $week, int $id): void
    {
        $this->authorizeCurrentWeek($workspace, $week);
        DB::transaction(function () use ($workspace, $week, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $activity = $workspace->routineActivities()->whereDate('week_start', $week)->findOrFail($id);
            $workspace->routineTasks()->findOrFail($activity->routine_task_id)->update(['ends_before' => $week->toDateString()]);
            $activity->delete();
        });
    }
}
