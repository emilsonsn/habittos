<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\Weekday;
use App\Models\Workspace;
use App\Services\ManageRoutine;
use App\Services\RoutineCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class RoutineBoard extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Url]
    public int $year = 0;

    #[Url]
    public int $week = 0;

    public array $titles = [];

    public string $message = '';

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $today = app(RoutineCalendar::class)->currentWeek();
        $this->year = $this->year ?: $today->isoWeekYear();
        $this->week = $this->week ?: $today->isoWeek();
    }

    private function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize(Permission::RoutineView->value, $workspace);

        return $workspace;
    }

    private function weekStart(): CarbonImmutable
    {
        return app(RoutineCalendar::class)->start($this->year, $this->week);
    }

    public function navigateWeek(int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $this->applyWeek($this->weekStart()->addWeeks($direction));
    }

    public function openCurrentWeek(): void
    {
        $this->applyWeek(app(RoutineCalendar::class)->currentWeek());
    }

    private function applyWeek(CarbonImmutable $date): void
    {
        app(RoutineCalendar::class)->start($date->isoWeekYear(), $date->isoWeek());
        $this->year = $date->isoWeekYear();
        $this->week = $date->isoWeek();
        $this->reset('titles', 'message');
        $this->resetValidation();
    }

    public function addTask(int $weekday): void
    {
        try {
            app(ManageRoutine::class)->add($this->workspace(), $this->weekStart(), $weekday, (string) ($this->titles[$weekday] ?? ''));
        } catch (ValidationException $exception) {
            $this->addError('titles.'.$weekday, $exception->validator->errors()->first());

            return;
        }
        unset($this->titles[$weekday]);
        $this->resetValidation('titles.'.$weekday);
        $this->message = 'Atividade adicionada à rotina.';
    }

    public function synchronizePreviousWeek(): void
    {
        $this->reset('message');
        $this->resetValidation('synchronization');
        $count = app(ManageRoutine::class)->synchronizePreviousWeek($this->workspace(), $this->weekStart());
        $this->message = "Semana sincronizada: {$count} atividade(s) copiadas, todas desmarcadas.";
    }

    public function setCompleted(int $id, bool $completed): void
    {
        app(ManageRoutine::class)->setCompleted($this->workspace(), $this->weekStart(), $id, $completed);
        $this->message = $completed ? 'Atividade concluída.' : 'Atividade desmarcada.';
    }

    public function removeTask(int $id): void
    {
        app(ManageRoutine::class)->remove($this->workspace(), $this->weekStart(), $id);
        $this->message = 'Atividade removida da rotina. O histórico anterior foi preservado.';
    }

    public function render()
    {
        $workspace = $this->workspace();
        $weekStart = $this->weekStart();
        $current = app(RoutineCalendar::class)->currentWeek();
        $activities = app(ManageRoutine::class)->activities($workspace, $weekStart);
        $editable = $weekStart->isSameDay($current) && $workspace->allows(auth()->user(), Permission::RoutineManage);
        $canSynchronize = $weekStart->gte($current) && $activities->isEmpty() && $workspace->allows(auth()->user(), Permission::RoutineManage);
        $future = $weekStart->gt($current);
        $days = Weekday::cases();

        return view('livewire.routine-board', compact('workspace', 'weekStart', 'activities', 'editable', 'canSynchronize', 'future', 'days'));
    }
}
