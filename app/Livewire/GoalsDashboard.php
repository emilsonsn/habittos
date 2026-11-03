<?php

namespace App\Livewire;

use App\Enums\GoalPeriod;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class GoalsDashboard extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Url(as: 'year')]
    public int $year = 0;

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->year = $this->year ?: now()->year;
    }

    public function render()
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize('goals.view', $workspace);
        $this->validate(['year' => ['required', 'integer', 'between:2000,2100']]);
        $goals = $workspace->goals()->where('year', $this->year)->get();
        $total = $goals->count();
        $completed = $goals->where('status', GoalStatus::Completed->value)->count();
        $inProgress = $goals->where('status', GoalStatus::InProgress->value)->count();
        $average = $total ? round($goals->avg('progress_percent'), 1) : 0;
        $today = today();
        $pending = $goals->where('status', '!=', GoalStatus::Completed->value);
        $overdue = $pending->filter(fn ($goal) => $goal->due_date?->lt($today))->sortBy('due_date');
        $upcoming = $pending->filter(fn ($goal) => $goal->due_date?->gte($today))->sortBy('due_date')->take(6);
        $byPeriod = collect(GoalPeriod::options())->map(fn ($label, $period) => [
            'label' => $label, 'total' => $goals->where('period', $period)->count(),
            'completed' => $goals->where('period', $period)->where('status', GoalStatus::Completed->value)->count(),
            'average' => round($goals->where('period', $period)->avg('progress_percent') ?? 0, 1),
            'in_progress' => $goals->where('period', $period)->where('status', GoalStatus::InProgress->value)->count(),
        ]);
        $byCategory = $goals->groupBy(fn ($goal) => $goal->category ?? 'Sem categoria')
            ->map(fn ($items) => ['total' => $items->count(), 'completed' => $items->where('status', GoalStatus::Completed->value)->count(), 'average' => round($items->avg('progress_percent'), 1)])
            ->sortByDesc('total');

        $byStatus = collect(GoalStatus::options())->map(fn ($label, $status) => [
            'name' => $label, 'count' => $goals->where('status', $status)->count(), 'color' => GoalStatus::from($status)->color(),
        ]);
        $byPriority = collect(GoalPriority::options())->map(fn ($label, $priority) => [
            'label' => $label, 'total' => $goals->where('priority', $priority)->count(),
            'statuses' => collect(GoalStatus::options())->map(fn ($name, $status) => [
                'name' => $name, 'count' => $goals->where('priority', $priority)->where('status', $status)->count(), 'color' => GoalStatus::from($status)->color(),
            ]),
        ]);

        return view('livewire.goals-dashboard', compact('workspace', 'goals', 'total', 'completed', 'inProgress', 'average', 'overdue', 'upcoming', 'byPeriod', 'byCategory', 'byStatus', 'byPriority'));
    }
}
