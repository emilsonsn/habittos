<?php

namespace Tests\Feature;

use App\Livewire\GoalsBoard;
use App\Livewire\NotesLibrary;
use App\Livewire\WorkspaceSettings;
use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\ManageGoals;
use App\Services\ManageNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmationDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_mounts_one_accessible_shared_dialog_and_loads_its_assets(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $response = $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('confirmation.js')->assertSee('confirmation.css')
            ->assertSee('role="alertdialog"', false)->assertSee('aria-describedby="action-confirmation-dialog-description"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="action-confirmation-dialog"'));
    }

    public function test_goal_and_note_deletions_include_context_without_native_confirmations(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $goal = app(ManageGoals::class)->save($workspace, [
            'title' => 'Meta & planos', 'period' => 'annual', 'year' => 2026,
            'status' => 'not_started', 'priority' => 'medium', 'progress_percent' => 0,
        ]);
        Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])->set('yearFilter', 2026)
            ->call('openEdit', $goal->id)->assertSee('data-confirm-title="Excluir meta?"', false)
            ->assertSee('data-confirm-subject="Meta &amp; planos"', false)->assertDontSee('wire:confirm', false);
        $note = app(ManageNotes::class)->saveNote($workspace, ['title' => 'Anotações', 'text' => 'Conteúdo']);
        Livewire::test(NotesLibrary::class, ['workspaceId' => $workspace->id])->call('openNote', $note->id)
            ->assertSee('data-confirm-title="Excluir nota?"', false)->assertSee('data-confirm-subject="Anotações"', false)
            ->assertDontSee('wire:confirm', false);
        $this->assertDatabaseHas('goals', ['id' => $goal->id]);
        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }

    public function test_workspace_uses_shared_dialog_and_retains_typed_server_confirmation(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa & planos');
        $this->actingAs($user);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->assertSee('confirmation-dialog', false)->assertSee('id="delete-space"', false)
            ->assertSee('wire:model="confirmation"', false)->assertSee('notas')
            ->set('confirmation', 'Errado')->call('deleteWorkspace')->assertHasErrors('confirmation');
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }
}
