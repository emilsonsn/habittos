<?php

namespace Tests\Feature;

use App\Enums\NoteSection;
use App\Livewire\NotesLibrary;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\ManageNotes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotesModuleTest extends TestCase
{
    use RefreshDatabase;

    private function assertMissingModel(callable $action): void
    {
        try {
            $action();
            $this->fail('O registro de outro ambiente deve ser inacessível.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    private function workspace(): Workspace
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Pessoal');
    }

    private function folder(Workspace $workspace, string $name = 'Projetos', ?int $parentId = null)
    {
        return app(ManageNotes::class)->saveCategory($workspace, ['name' => $name, 'parent_id' => $parentId]);
    }

    private function note(Workspace $workspace, string $title = 'Uma ideia', ?int $categoryId = null)
    {
        return app(ManageNotes::class)->saveNote($workspace, ['title' => $title, 'text' => '<p>Texto <strong>importante</strong></p>', 'category_id' => $categoryId]);
    }

    public function test_three_pages_navigation_and_switcher_keep_the_notes_module(): void
    {
        $workspace = $this->workspace();
        foreach (NoteSection::cases() as $section) {
            $response = $this->get(route($section->routeName(), $workspace))->assertOk();
            foreach (NoteSection::cases() as $item) {
                $response->assertSee(route($item->routeName(), $workspace), false);
            }
            Livewire::test(WorkspaceSwitcher::class, ['activeId' => $workspace->id, 'module' => $section->routeName()])
                ->call('selectWorkspace', $workspace->id)->assertRedirect(route($section->routeName(), $workspace));
        }
        $this->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'notes.favorites'])
            ->assertRedirect(route('notes.favorites', $workspace));
    }

    public function test_notes_can_be_created_at_root_and_inherit_the_current_folder_and_keep_formatting(): void
    {
        $workspace = $this->workspace();
        $parent = $this->folder($workspace);
        $child = $this->folder($workspace, 'Habittos', $parent->id);
        $component = Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->call('openCreateNote')->assertSet('noteForm.category_id', null)->set('noteForm.title', 'Nota na raiz')
            ->call('saveNote')->assertHasNoErrors()->assertSet('noteOpen', false)
            ->set('categoryId', $child->id)->assertSee('Habittos')->assertDontSee('Nota na raiz')
            ->call('openCreateNote')->assertSet('noteForm.category_id', $child->id)
            ->set('noteForm.title', 'Detalhes do projeto')->set('noteForm.text', '<p><em>Fazer</em> <u>hoje</u></p>')
            ->call('saveNote')->assertHasNoErrors()->assertSee('Detalhes do projeto');
        $note = $workspace->notes()->where('title', 'Detalhes do projeto')->firstOrFail();
        $this->assertSame($child->id, $note->category_id);
        $this->assertSame('<p><em>Fazer</em> <u>hoje</u></p>', $note->text);
        $component->call('openNote', $note->id)->assertSet('noteForm.text', $note->text)
            ->set('noteForm.title', 'Descartada')->call('closeForms')->call('openNote', $note->id)
            ->assertSet('noteForm.title', 'Detalhes do projeto')
            ->set('noteForm.title', 'Movida para a raiz')->set('noteForm.category_id', '')
            ->call('saveNote')->assertHasNoErrors();
        $this->assertNull($note->fresh()->category_id);
        $this->assertSame(2, $workspace->notes()->count());
    }

    public function test_subfolders_are_created_inside_current_folder_and_can_be_renamed_and_moved(): void
    {
        $workspace = $this->workspace();
        $parent = $this->folder($workspace);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->set('categoryId', $parent->id)->call('openCategory')->assertSet('categoryForm.parent_id', $parent->id)
            ->set('categoryForm.name', 'Filha')->call('saveCategory')->assertHasNoErrors()->assertSee('Filha');
        $child = $workspace->noteCategories()->where('name', 'Filha')->firstOrFail();
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->call('openCategory', $child->id)->set('categoryForm.name', 'Renomeada')->set('categoryForm.parent_id', '')
            ->call('saveCategory')->assertHasNoErrors()->assertSee('Renomeada');
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_folder_cannot_be_its_own_parent_or_move_inside_a_descendant(): void
    {
        $workspace = $this->workspace();
        $parent = $this->folder($workspace);
        $child = $this->folder($workspace, 'Filha', $parent->id);
        $grandchild = $this->folder($workspace, 'Neta', $child->id);
        $component = Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->call('openCategory', $parent->id)->set('categoryForm.parent_id', $parent->id)
            ->call('saveCategory')->assertHasErrors('categoryForm.parent_id')
            ->set('categoryForm.parent_id', $grandchild->id)->call('saveCategory')->assertHasErrors('categoryForm.parent_id');
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_deleting_a_folder_preserves_notes_and_subfolders_in_its_parent(): void
    {
        $workspace = $this->workspace();
        $parent = $this->folder($workspace);
        $child = $this->folder($workspace, 'Filha', $parent->id);
        $grandchild = $this->folder($workspace, 'Neta', $child->id);
        $note = $this->note($workspace, 'Preservar', $child->id);
        app(ManageNotes::class)->toggleFavorite($workspace, $note->id);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->set('categoryId', $child->id)->call('deleteCategory', $child->id)->assertSet('categoryId', $parent->id)
            ->assertSee('Preservar')->assertSee('Neta');
        $this->assertSame($parent->id, $note->fresh()->category_id);
        $this->assertSame($parent->id, $grandchild->fresh()->parent_id);
        app(ManageNotes::class)->deleteCategory($workspace, $parent->id);
        $this->assertNull($note->fresh()->category_id);
        $this->assertNull($grandchild->fresh()->parent_id);
        app(ManageNotes::class)->deleteNote($workspace, $note->id);
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_favorites_are_shared_by_workspace_and_readers_cannot_change_them(): void
    {
        $workspace = $this->workspace();
        $note = $this->note($workspace, 'Referência');
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['notes.view']]);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'favorites'])
            ->assertViewHas('notes', fn ($notes) => $notes->isEmpty())->call('toggleFavorite', $note->id)
            ->assertViewHas('notes', fn ($notes) => $notes->pluck('id')->all() === [$note->id]);
        $this->actingAs($reader);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'favorites'])
            ->assertSee('Referência')->assertViewHas('stats', fn ($stats) => $stats['favorites'] === 1)
            ->call('openNote', $note->id)->assertSee('Ler nota')
            ->assertSee('<p>Texto <strong>importante</strong></p>', false)->assertDontSee('contenteditable="true"', false)
            ->assertDontSee('Salvar alterações')->set('noteForm.title', 'Proibido')->call('saveNote')->assertForbidden();
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('toggleFavorite', $note->id)->assertForbidden();
        $this->assertTrue($note->fresh()->is_favorite);
        $this->assertSame('Referência', $note->fresh()->title);
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['notes.view', 'notes.manage']]);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'favorites'])
            ->call('toggleFavorite', $note->id)->assertViewHas('notes', fn ($notes) => $notes->isEmpty());
        $this->actingAs($workspace->members()->findOrFail($workspace->owner_id));
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'favorites'])
            ->assertViewHas('notes', fn ($notes) => $notes->isEmpty());
    }

    public function test_search_finds_notes_across_folders_and_dashboard_only_counts_current_workspace(): void
    {
        $workspace = $this->workspace();
        $folder = $this->folder($workspace);
        $note = $this->note($workspace, 'Nota categorizada', $folder->id);
        $this->note($workspace, 'Nota inicial');
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $private = $this->note($other, 'Privada');
        app(ManageNotes::class)->toggleFavorite($other, $private->id);
        app(ManageNotes::class)->toggleFavorite($workspace, $note->id);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->assertViewHas('notes', fn ($notes) => $notes->total() === 1)->assertDontSee('Nota categorizada')
            ->set('search', 'categorizada')->assertSee('Nota categorizada')->assertDontSee('Privada');
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])
            ->assertViewHas('stats', ['notes' => 2, 'folders' => 1, 'favorites' => 1, 'uncategorized' => 1])
            ->assertDontSee('Privada');
    }

    public function test_cross_workspace_note_ids_are_rejected_for_read_edit_delete_and_favorite(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $foreign = $this->note($other, 'Privada');
        foreach (['openNote', 'deleteNote', 'toggleFavorite'] as $action) {
            $this->assertMissingModel(fn () => Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call($action, $foreign->id));
        }
        $this->assertNotNull($foreign->fresh());
    }

    public function test_foreign_folder_cannot_be_opened_assigned_moved_or_deleted(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $foreign = $this->folder($other, 'Privada');
        $this->assertMissingModel(fn () => Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->set('categoryId', $foreign->id));
        foreach (['openCategory', 'deleteCategory'] as $action) {
            $this->assertMissingModel(fn () => Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call($action, $foreign->id));
        }
        $this->assertMissingModel(fn () => Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('openCreateNote')
            ->set('noteForm.title', 'Inválida')->set('noteForm.category_id', $foreign->id)->call('saveNote'));
        $this->assertMissingModel(fn () => Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('openCategory')
            ->set('categoryForm.name', 'Inválida')->set('categoryForm.parent_id', $foreign->id)->call('saveCategory'));
        $this->assertSame(0, $workspace->notes()->count());
    }

    public function test_permissions_and_membership_are_rechecked_when_saving_and_favoriting(): void
    {
        $workspace = $this->workspace();
        $note = $this->note($workspace);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['notes.view']]);
        $this->actingAs($reader);
        foreach (['openCreateNote' => [], 'openCategory' => [], 'deleteNote' => [$note->id]] as $action => $args) {
            Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call($action, ...$args)->assertForbidden();
        }
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['notes.view', 'notes.manage']]);
        $component = Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('openNote', $note->id);
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['notes.view']]);
        $component->set('noteForm.title', 'Proibida')->call('saveNote')->assertForbidden();
        app(CreateWorkspace::class)->handle($reader, 'Outro ambiente');
        $workspace->members()->detach($reader);
        Livewire::actingAs($reader);
        $this->get(route('notes.index', $workspace))->assertNotFound();
        $this->assertSame('Uma ideia', $note->fresh()->title);
    }

    public function test_validation_and_sanitization_preserve_safe_text_and_cancel_does_not_save(): void
    {
        $workspace = $this->workspace();
        $component = Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('openCreateNote')
            ->set('noteForm.title', '')->call('saveNote')->assertHasErrors('noteForm.title')
            ->set('noteForm.title', '  Texto seguro  ')->set('noteForm.text', str_repeat('a', 100001))
            ->call('saveNote')->assertHasErrors('noteForm.text')
            ->set('noteForm.text', '<p onclick="alert(1)">Olá <b>mundo</b></p><script>alert(1)</script>')
            ->call('saveNote')->assertHasNoErrors();
        $note = $workspace->notes()->firstOrFail();
        $this->assertSame('<p>Olá <b>mundo</b></p>', $note->text);
        $this->assertSame('Texto seguro', $note->title);
        $this->assertSame('Olá mundo', $note->excerpt());
        $component->call('openCreateNote')->set('noteForm.title', 'Descartada')->call('closeForms');
        $this->assertSame(1, $workspace->notes()->count());
    }

    public function test_notes_and_folders_are_removed_with_their_workspace(): void
    {
        $workspace = $this->workspace();
        $folder = $this->folder($workspace);
        $note = $this->note($workspace, 'Excluir junto', $folder->id);
        app(ManageNotes::class)->toggleFavorite($workspace, $note->id);
        $workspace->delete();
        $this->assertDatabaseCount('notes', 0);
        $this->assertDatabaseCount('note_categories', 0);
    }

    public function test_notes_pages_and_menu_require_the_notes_view_permission(): void
    {
        $workspace = $this->workspace();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view', 'notes.manage']]);
        $this->actingAs($reader);
        foreach (NoteSection::cases() as $section) {
            $this->get(route($section->routeName(), $workspace))->assertForbidden();
        }
        $this->get(route('home'))->assertOk()->assertDontSee('Seções de notas');
    }

    public function test_browse_paginates_and_resets_the_page_when_search_changes(): void
    {
        $workspace = $this->workspace();
        foreach (range(1, 13) as $number) {
            $this->note($workspace, 'Registro '.$number);
        }
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id, 'section' => 'browse'])
            ->assertViewHas('notes', fn ($notes) => $notes->total() === 13 && $notes->count() === 12)
            ->call('nextPage')->assertViewHas('notes', fn ($notes) => $notes->currentPage() === 2 && $notes->count() === 1)
            ->set('search', 'Registro 13')->assertViewHas('notes', fn ($notes) => $notes->currentPage() === 1 && $notes->total() === 1);
    }
}
