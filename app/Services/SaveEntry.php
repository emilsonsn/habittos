<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Entry;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveEntry
{
    public function handle(Workspace $workspace, array $data, ?int $entryId = null): Entry
    {
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize($entryId ? Permission::FinanceUpdate->value : Permission::FinanceCreate->value, $workspace);
        if (isset($data['notes']) && is_string($data['notes'])) {
            $data['notes'] = trim($data['notes']) !== '' ? trim($data['notes']) : null;
        }

        return DB::transaction(function () use ($workspace, $data, $entryId) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            if ($entryId) {
                $entry = $workspace->entries()->findOrFail($entryId);
                $entry->update($data);

                return $entry;
            }

            return $workspace->entries()->create($data);
        }, attempts: 5);
    }
}
