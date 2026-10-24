<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesWorkspaceCover;
use App\Models\Workspace;
use App\Services\WorkspaceCover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WorkspaceSettings extends Component
{
    use HandlesWorkspaceCover;

    public function saveCover(): void
    {
        $workspace = $this->workspace();
        abort_unless($workspace->owner_id === auth()->id(), 403);
        $this->validate(['cover' => ['required', ...WorkspaceCover::rules()]]);
        $this->validateCoverDraft();
        app(WorkspaceCover::class)->save($workspace, $this->cover, (int) $this->positionX, (int) $this->positionY);
        $this->resetCoverDraft();
        $this->message = 'Capa do ambiente atualizada.';
    }

    public function removeCover(): void
    {
        $workspace = $this->workspace();
        abort_unless($workspace->owner_id === auth()->id(), 403);
        app(WorkspaceCover::class)->save($workspace, null);
        $this->resetCoverDraft();
        $this->message = 'Capa do ambiente removida.';
    }

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
        if ($workspace->cover_path) {
            Storage::disk('local')->delete($workspace->cover_path);
        }
        session()->forget('active_workspace_id');
        session()->flash('success', 'Ambiente excluído.');
        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workspace-settings', ['workspace' => $this->workspace()]);
    }
}
