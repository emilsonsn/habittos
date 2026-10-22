<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\Workspace;
use App\Services\ManageMembers;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MembersManager extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $email = '';

    public array $permissions = [];

    public array $memberPermissions = [];

    public string $message = '';

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize('members.manage', $workspace);

        return $workspace;
    }

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        foreach ($this->workspace()->members()->get() as $member) {
            $this->memberPermissions[$member->id] = $member->pivot->permissions ?? [];
        }
    }

    public function addMember(): void
    {
        app(ManageMembers::class)->save($this->workspace(), ['email' => trim($this->email), 'permissions' => $this->permissions]);
        $member = $this->workspace()->members()->where('email', trim($this->email))->firstOrFail();
        $this->memberPermissions[$member->id] = $this->permissions;
        $this->reset('email', 'permissions');
        $this->message = 'Acesso concedido.';
    }

    public function savePermissions(int $id): void
    {
        $workspace = $this->workspace();
        $member = $workspace->members()->whereKey($id)->firstOrFail();
        app(ManageMembers::class)->save($workspace, ['email' => $member->email, 'permissions' => $this->memberPermissions[$id] ?? []]);
        $this->message = 'Permissões atualizadas.';
    }

    public function removeMember(int $id): void
    {
        app(ManageMembers::class)->remove($this->workspace(), $id);
        unset($this->memberPermissions[$id]);
        $this->message = 'Acesso removido.';
    }

    public function render()
    {
        $workspace = $this->workspace();

        return view('livewire.members-manager', ['workspace' => $workspace, 'members' => $workspace->members()->get(), 'permissionOptions' => Permission::cases()]);
    }
}
