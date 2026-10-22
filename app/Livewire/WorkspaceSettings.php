<?php

namespace App\Livewire;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WorkspaceSettings extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $name = '';

    public string $confirmation = '';

    public string $message = '';

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->name = $this->workspace()->name;
    }

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);

        return auth()->user()->workspaces()->findOrFail($this->workspaceId);
    }

    public function save(): void
    {
        $workspace = $this->workspace();
        abort_unless($workspace->owner_id === auth()->id(), 403);
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:100']]);
        $workspace->update(['name' => $this->name]);
        session()->flash('success', 'Nome do ambiente atualizado.');
        $this->redirect(route('settings.index', $workspace), navigate: true);
    }

    public function deleteWorkspace(): void
    {
        $workspace = $this->workspace();
        abort_unless($workspace->owner_id === auth()->id(), 403);
        if ($this->confirmation !== $workspace->name) {
            $this->addError('confirmation', 'Digite o nome exato do ambiente para confirmar.');

            return;
        }
        DB::transaction(fn () => $workspace->delete());
        session()->forget('active_workspace_id');
        session()->flash('success', 'Ambiente excluído.');
        $this->redirect(route('workspaces.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workspace-settings', ['workspace' => $this->workspace()]);
    }
}
