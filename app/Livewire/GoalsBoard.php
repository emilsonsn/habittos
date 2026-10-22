<?php

namespace App\Livewire;

use App\Models\Workspace;
use App\Services\ManageGoals;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GoalsBoard extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $title = '';

    public string $period = 'annual';

    public int $year;

    public ?int $month = null;

    public string $target = '';

    public array $progress = [];

    public string $message = '';

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize('goals.view', $workspace);

        return $workspace;
    }

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->year = now()->year;
        foreach ($this->workspace()->goals()->get() as $goal) {
            $this->progress[$goal->id] = (string) ($goal->current_cents / 100);
        }
    }

    public function createGoal(): void
    {
        $workspace = $this->workspace();
        Gate::authorize('goals.manage', $workspace);
        $this->title = trim($this->title);
        $data = $this->validate(ManageGoals::rules());
        app(ManageGoals::class)->create($workspace, $data);
        $this->reset('title', 'target', 'month');
        $this->message = 'Meta criada.';
    }

    public function updateProgress(int $id): void
    {
        $workspace = $this->workspace();
        Gate::authorize('goals.manage', $workspace);
        $this->validate(['progress.'.$id => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99']]);
        app(ManageGoals::class)->progress($workspace, $id, (string) $this->progress[$id]);
        $this->message = 'Progresso atualizado.';
    }

    public function deleteGoal(int $id): void
    {
        app(ManageGoals::class)->delete($this->workspace(), $id);
        unset($this->progress[$id]);
        $this->message = 'Meta excluída.';
    }

    public function render()
    {
        $workspace = $this->workspace();

        return view('livewire.goals-board', ['workspace' => $workspace, 'goals' => $workspace->goals()->orderByDesc('year')->orderBy('month')->get()]);
    }
}
