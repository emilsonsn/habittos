<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageNotes
{
    private function authorize(Workspace $workspace): void
    {
        abort_unless(auth()->check(), 401);
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize('notes.view', $workspace);
        Gate::authorize('notes.manage', $workspace);
    }

    public function saveNote(Workspace $workspace, array $input, ?int $id = null): Note
    {
        $this->authorize($workspace);

        return DB::transaction(function () use ($workspace, $input, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $note = $id ? $workspace->notes()->findOrFail($id) : null;
            $input['title'] = trim((string) ($input['title'] ?? ''));
            $data = validator($input, [
                'title' => ['required', 'string', 'max:200'],
                'text' => ['nullable', 'string', 'max:100000'],
                'category_id' => ['nullable', 'integer'],
            ], attributes: ['title' => 'título', 'text' => 'texto', 'category_id' => 'pasta'])->validate();
            $data['category_id'] = empty($data['category_id']) ? null : $workspace->noteCategories()->findOrFail($data['category_id'])->id;
            $data['text'] = app(RichText::class)->sanitize($data['text'] ?? null);
            if ($note) {
                $note->update($data);

                return $note->fresh();
            }

            return $workspace->notes()->create($data);
        });
    }

    public function saveCategory(Workspace $workspace, array $input, ?int $id = null): NoteCategory
    {
        $this->authorize($workspace);

        return DB::transaction(function () use ($workspace, $input, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $category = $id ? $workspace->noteCategories()->findOrFail($id) : null;
            $input['name'] = trim((string) ($input['name'] ?? ''));
            $data = validator($input, [
                'name' => ['required', 'string', 'max:100'], 'parent_id' => ['nullable', 'integer'],
            ], attributes: ['name' => 'nome da pasta', 'parent_id' => 'pasta superior'])->validate();
            $data['parent_id'] = empty($data['parent_id']) ? null : (int) $data['parent_id'];
            $parentId = $data['parent_id'];
            $visited = [];
            while ($parentId) {
                if ($parentId === $category?->id || isset($visited[$parentId])) {
                    throw ValidationException::withMessages(['parent_id' => 'A pasta não pode ficar dentro dela mesma ou de uma de suas subpastas.']);
                }
                $visited[$parentId] = true;
                $parentId = $workspace->noteCategories()->findOrFail($parentId)->parent_id;
            }
            if ($category) {
                $category->update($data);

                return $category->fresh();
            }

            return $workspace->noteCategories()->create($data);
        });
    }

    public function deleteCategory(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        DB::transaction(function () use ($workspace, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $category = $workspace->noteCategories()->findOrFail($id);
            $workspace->notes()->where('category_id', $id)->update(['category_id' => $category->parent_id]);
            $workspace->noteCategories()->where('parent_id', $id)->update(['parent_id' => $category->parent_id]);
            $category->delete();
        });
    }

    public function deleteNote(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        DB::transaction(function () use ($workspace, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $workspace->notes()->findOrFail($id)->delete();
        });
    }

    public function toggleFavorite(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        DB::transaction(function () use ($workspace, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $note = $workspace->notes()->findOrFail($id);
            $note->forceFill(['is_favorite' => ! $note->is_favorite])->save();
        });
    }
}
