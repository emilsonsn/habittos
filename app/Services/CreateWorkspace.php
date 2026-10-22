<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function handle(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name) {
            $workspace = Workspace::create(['name' => $name, 'owner_id' => $owner->id]);
            $workspace->members()->attach($owner, ['permissions' => []]);
            foreach (['Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Lazer', 'Família', 'Assinaturas', 'Outros'] as $category) {
                $workspace->categories()->create(['name' => $category]);
            }

            return $workspace;
        });
    }
}
