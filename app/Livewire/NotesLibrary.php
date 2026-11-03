<?php

namespace App\Livewire;

use App\Enums\NoteSection;
use App\Models\NoteCategory;
use App\Models\Workspace;
use App\Services\ManageNotes;
use App\Services\RichText;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class NotesLibrary extends Component
{
    use WithPagination;

    #[Locked]
    public int $workspaceId;

    #[Locked]
    public string $section = 'dashboard';

    #[Url(as: 'category')]
    public ?int $categoryId = null;

    #[Url]
    public string $search = '';

    #[Locked]
    public bool $noteOpen = false;

    #[Locked]
    public bool $categoryOpen = false;

    #[Locked]
    public ?int $editingNoteId = null;

    #[Locked]
    public ?int $editingCategoryId = null;

    #[Locked]
    public int $formVersion = 0;

    public array $noteForm = [];

    public array $categoryForm = [];

    public string $message = '';

    public function mount(int $workspaceId, string $section = 'dashboard'): void
    {
        $this->workspaceId = $workspaceId;
        $this->section = NoteSection::from($section)->value;
        $this->workspace();
    }

    private function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize('notes.view', $workspace);

        return $workspace;
    }

    private function currentCategory(Workspace $workspace): ?NoteCategory
    {
        return $this->section === 'browse' && $this->categoryId !== null
            ? $workspace->noteCategories()->findOrFail($this->categoryId)
            : null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->currentCategory($this->workspace());
        $this->closeForms();
        $this->resetPage();
    }

    public function openCreateNote(): void
    {
        $workspace = $this->workspace();
        Gate::authorize('notes.manage', $workspace);
        $category = $this->currentCategory($workspace);
        $this->closeForms();
        $this->noteForm = ['title' => '', 'text' => '', 'category_id' => $category?->id];
        $this->formVersion++;
        $this->noteOpen = true;
    }

    public function openNote(int $id): void
    {
        $note = $this->workspace()->notes()->findOrFail($id);
        $this->closeForms();
        $this->editingNoteId = $note->id;
        $this->noteForm = ['title' => $note->title, 'text' => app(RichText::class)->sanitize($note->text) ?? '', 'category_id' => $note->category_id];
        $this->formVersion++;
        $this->noteOpen = true;
    }

    public function saveNote(): void
    {
        $workspace = $this->workspace();
        Gate::authorize('notes.manage', $workspace);
        abort_unless($this->noteOpen, 422);
        $this->resetValidation();
        try {
            app(ManageNotes::class)->saveNote($workspace, $this->noteForm, $this->editingNoteId);
        } catch (ValidationException $exception) {
            $this->formErrors($exception, 'noteForm');

            return;
        }
        $this->message = $this->editingNoteId ? 'Nota atualizada.' : 'Nota criada.';
        $this->closeForms();
        $this->resetPage();
    }

    public function openCategory(?int $id = null): void
    {
        $workspace = $this->workspace();
        Gate::authorize('notes.manage', $workspace);
        $current = $this->currentCategory($workspace);
        $category = $id ? $workspace->noteCategories()->findOrFail($id) : null;
        $this->closeForms();
        $this->editingCategoryId = $category?->id;
        $this->categoryForm = ['name' => $category?->name ?? '', 'parent_id' => $category ? $category->parent_id : $current?->id];
        $this->formVersion++;
        $this->categoryOpen = true;
    }

    public function saveCategory(): void
    {
        $workspace = $this->workspace();
        Gate::authorize('notes.manage', $workspace);
        abort_unless($this->categoryOpen, 422);
        $this->resetValidation();
        try {
            app(ManageNotes::class)->saveCategory($workspace, $this->categoryForm, $this->editingCategoryId);
        } catch (ValidationException $exception) {
            $this->formErrors($exception, 'categoryForm');

            return;
        }
        $this->message = $this->editingCategoryId ? 'Pasta atualizada.' : 'Pasta criada.';
        $this->closeForms();
    }

    private function formErrors(ValidationException $exception, string $prefix): void
    {
        foreach ($exception->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($prefix.'.'.$key, $message);
            }
        }
    }

    public function deleteNote(int $id): void
    {
        app(ManageNotes::class)->deleteNote($this->workspace(), $id);
        $this->closeForms();
        $this->resetPage();
        $this->message = 'Nota excluída.';
    }

    public function deleteCategory(int $id): void
    {
        $workspace = $this->workspace();
        $category = $workspace->noteCategories()->findOrFail($id);
        $parentId = $category->parent_id;
        app(ManageNotes::class)->deleteCategory($workspace, $id);
        if ($this->categoryId === $id) {
            $this->categoryId = $parentId;
        }
        $this->closeForms();
        $this->resetPage();
        $this->message = 'Pasta excluída. Notas e subpastas foram preservadas na pasta superior.';
    }

    public function toggleFavorite(int $id): void
    {
        app(ManageNotes::class)->toggleFavorite($this->workspace(), $id);
        $this->resetPage();
    }

    public function closeForms(): void
    {
        $this->reset('noteOpen', 'categoryOpen', 'editingNoteId', 'editingCategoryId', 'noteForm', 'categoryForm');
        $this->resetValidation();
    }

    public function render()
    {
        $workspace = $this->workspace();
        $category = $this->currentCategory($workspace);
        validator(['search' => $this->search], ['search' => ['string', 'max:200']])->validate();
        $allCategories = $workspace->noteCategories()->withCount(['notes', 'children'])->orderBy('name')->get()->keyBy('id');
        $breadcrumbs = collect();
        $cursor = $category;
        while ($cursor) {
            $breadcrumbs->prepend($cursor);
            $cursor = $allCategories->get($cursor->parent_id);
        }
        $categoryOptions = [];
        foreach ($allCategories as $folder) {
            $names = [];
            $ids = [];
            $cursor = $folder;
            while ($cursor) {
                array_unshift($names, $cursor->name);
                $ids[] = $cursor->id;
                $cursor = $allCategories->get($cursor->parent_id);
            }
            $categoryOptions[] = ['id' => $folder->id, 'label' => implode(' / ', $names), 'available_parent' => ! in_array($this->editingCategoryId, $ids, true)];
        }
        usort($categoryOptions, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
        $folders = $allCategories->where('parent_id', $category?->id);
        $notesQuery = $workspace->notes()->with('category');
        if ($this->section === 'favorites') {
            $notesQuery->where('is_favorite', true);
        }
        if (trim($this->search) !== '') {
            $search = '%'.trim($this->search).'%';
            $notesQuery->where(fn ($query) => $query->where('title', 'like', $search)->orWhere('text', 'like', $search));
        } elseif ($this->section === 'browse') {
            $notesQuery->where('category_id', $category?->id);
        }
        $notesQuery->orderByDesc('updated_at')->orderByDesc('id');
        $notes = $this->section === 'dashboard' ? $notesQuery->limit(6)->get() : $notesQuery->paginate(12);
        $stats = [
            'notes' => $workspace->notes()->count(), 'folders' => $allCategories->count(),
            'favorites' => $workspace->notes()->where('is_favorite', true)->count(),
            'uncategorized' => $workspace->notes()->whereNull('category_id')->count(),
        ];

        return view('livewire.notes-library', compact('workspace', 'category', 'breadcrumbs', 'folders', 'categoryOptions', 'notes', 'stats'));
    }
}
