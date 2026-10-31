<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesWorkspaceCover;
use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WorkspaceOnboarding extends Component
{
    use HandlesWorkspaceCover;

    public string $name = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
        if (auth()->user()->workspaces()->exists()) {
            $this->redirectRoute('home', navigate: true);
        }
    }

    public function createWorkspace(): void
    {
        abort_unless(auth()->check(), 401);
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:100']]);
        $this->validateCoverDraft();
        $workspace = DB::transaction(function () {
            $user = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

            return $user->workspaces()->first() ?? app(CreateWorkspace::class)->handle(
                $user, $this->name, $this->cover, (int) $this->positionX, (int) $this->positionY,
            );
        });
        session(['active_workspace_id' => $workspace->id]);
        $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        abort_unless(auth()->check(), 401);

        return view('livewire.workspace-onboarding');
    }
}
