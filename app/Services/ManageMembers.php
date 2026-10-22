<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ManageMembers
{
    protected function authorize(Workspace $workspace): void
    {
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize('members.manage', $workspace);
    }

    public function save(Workspace $workspace, array $input): void
    {
        $this->authorize($workspace);
        $data = validator($input, [
            'email' => ['required', 'email', 'exists:users,email'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ])->validate();
        $member = User::where('email', $data['email'])->firstOrFail();
        abort_if($member->id === $workspace->owner_id, 422, 'As permissões do proprietário são permanentes.');
        $isOwner = auth()->id() === $workspace->owner_id;
        $existing = $workspace->members()->whereKey($member->id)->first();
        abort_if(! $isOwner && (in_array('members.manage', $data['permissions'] ?? []) || in_array('members.manage', $existing?->pivot->permissions ?? [])), 403);
        $workspace->members()->syncWithoutDetaching([$member->id => ['permissions' => $data['permissions'] ?? []]]);
    }

    public function remove(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        abort_if($id === $workspace->owner_id, 422, 'O proprietário não pode ser removido.');
        $member = $workspace->members()->whereKey($id)->firstOrFail();
        abort_if(auth()->id() !== $workspace->owner_id && in_array('members.manage', $member->pivot->permissions ?? []), 403);
        $workspace->members()->detach($id);
    }
}
