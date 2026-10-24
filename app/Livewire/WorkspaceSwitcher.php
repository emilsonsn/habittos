<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesWorkspaceCover;
use App\Services\CreateWorkspace;
use App\Services\SelectWorkspace;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    use HandlesWorkspaceCover;

    #[Locked]
    public ?int $activeId = null;

    #[Locked]
    public string $module = 'finance';

    #[Locked]
    public string $month = '';

    public string $name = '';

    public function mount(?int $activeId = null, string $module = 'finance', ?string $month = null): void
    {
        abort_unless(auth()->check(), 401);
        $this->activeId = $activeId;
        $this->module = $module;
        $this->month = $month ?? now()->format('Y-m');
    }

    #[On('financial-month-changed')]
    public function updateMonth(string $month): void
    {
        validator(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();
        $this->month = $month;
    }

    public function selectWorkspace(int $id): void
    {
        abort_unless(auth()->check(), 401);
        $url = app(SelectWorkspace::class)->handle(auth()->user(), $id, $this->module, $this->month);
        $this->redirect($url, navigate: true);
    }

    public function createWorkspace(): void
    {
        abort_unless(auth()->check(), 401);
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:100']]);
        $this->validateCoverDraft();
        $workspace = app(CreateWorkspace::class)->handle(auth()->user(), $this->name, $this->cover, (int) $this->positionX, (int) $this->positionY);
        $url = app(SelectWorkspace::class)->handle(auth()->user(), $workspace->id, 'finance', $this->month);
        $this->redirect($url, navigate: true);
    }

    public function render()
    {
        abort_unless(auth()->check(), 401);
        $workspaces = auth()->user()->workspaces()->orderBy('name')->get();

        return view('livewire.workspace-switcher', [
            'workspaces' => $workspaces,
            'selected' => $workspaces->firstWhere('id', $this->activeId),
        ]);
    }
}
