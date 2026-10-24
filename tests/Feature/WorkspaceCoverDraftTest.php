<?php

namespace Tests\Feature;

use App\Livewire\WorkspaceSettings;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceCoverDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_opens_editor_and_confirmation_only_updates_preview_until_final_save(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa', UploadedFile::fake()->image('original.jpg'));
        $originalPath = $workspace->cover_path;
        $originalFile = Storage::disk('local')->get($originalPath);
        $this->actingAs($owner);
        $form = Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('cover', UploadedFile::fake()->image('nova.jpg'))->assertSet('positioning', true)
            ->assertSet('coverReady', false)->assertSee('Ajustar capa')->assertDontSee('Ajustar enquadramento')
            ->call('saveCover')->assertHasErrors('cover');
        $this->assertSame($originalPath, $workspace->fresh()->cover_path);
        $form->set('positionX', 20)->set('positionY', 80)->call('confirmCoverPosition')->assertHasNoErrors()
            ->assertSet('positioning', false)->assertSet('coverReady', true)
            ->assertSee('Prévia da nova capa do ambiente')->assertSee('object-position: 20% 80%', false);
        $this->assertSame($originalPath, $workspace->fresh()->cover_path);
        $this->assertSame(50, $workspace->fresh()->cover_position_y);
        $this->assertSame($originalFile, Storage::disk('local')->get($originalPath));
        $form->call('saveCover')->assertHasNoErrors()->assertSet('coverReady', false);
        $saved = $workspace->fresh();
        $this->assertNotSame($originalPath, $saved->cover_path);
        $this->assertSame(20, $saved->cover_position_x);
        $this->assertSame(80, $saved->cover_position_y);
        Storage::disk('local')->assertExists($saved->cover_path);
        Storage::disk('local')->assertMissing($originalPath);
        $this->withSession(['active_workspace_id' => $workspace->id])->get(route('home'))
            ->assertOk()->assertSee('object-position: 20% 80%', false)->assertDontSee('Ajustar enquadramento');
    }

    public function test_creation_saves_image_and_crop_together_and_cancel_keeps_cover_optional(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $form = Livewire::test(WorkspaceSwitcher::class)->set('name', 'Casa')
            ->set('cover', UploadedFile::fake()->image('capa.jpg'))->assertSet('positioning', true)
            ->set('positionX', 0)->set('positionY', 100)->call('confirmCoverPosition')->assertHasNoErrors();
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('workspace-covers'));
        $form->call('createWorkspace')->assertHasNoErrors()->assertRedirect();
        $workspace = $owner->workspaces()->firstOrFail();
        $this->assertSame(0, $workspace->cover_position_x);
        $this->assertSame(100, $workspace->cover_position_y);
        Storage::disk('local')->assertExists($workspace->cover_path);
        Livewire::test(WorkspaceSwitcher::class)->set('name', 'Sem capa')
            ->set('cover', UploadedFile::fake()->image('cancelada.jpg'))->call('cancelCoverPosition')
            ->assertSet('positioning', false)->assertSet('cover', null)->call('createWorkspace')->assertHasNoErrors();
        $this->assertNull($owner->workspaces()->where('name', 'Sem capa')->firstOrFail()->cover_path);
    }

    public function test_cancel_discards_selected_image_and_preserves_existing_crop(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa', UploadedFile::fake()->image('original.jpg'), 10, 75);
        $this->actingAs($owner);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('cover', UploadedFile::fake()->image('nova.jpg'))->set('positionY', 90)
            ->call('cancelCoverPosition')->assertSet('cover', null)->assertSet('coverReady', false)
            ->assertSet('positioning', false)->assertSee('Capa atual do ambiente')
            ->assertDontSee('Prévia da nova capa do ambiente');
        $this->assertSame($workspace->cover_path, $workspace->fresh()->cover_path);
        $this->assertSame(75, $workspace->fresh()->cover_position_y);
        Storage::disk('local')->assertExists($workspace->cover_path);
    }

    public function test_positions_are_validated_and_every_new_selection_requires_confirmation(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $form = Livewire::test(WorkspaceSwitcher::class)->set('name', 'Casa')
            ->set('cover', UploadedFile::fake()->image('primeira.jpg'));
        foreach ([-1, 101, 1.5, 'invalid'] as $value) {
            $form->set('positionX', $value)->call('confirmCoverPosition')->assertHasErrors('positionX')->assertSet('coverReady', false);
        }
        $form->set('positionX', 20)->set('positionY', 101)->call('confirmCoverPosition')->assertHasErrors('positionY')
            ->set('positionY', 80)->call('confirmCoverPosition')->assertHasNoErrors()->assertSet('coverReady', true)
            ->set('cover', UploadedFile::fake()->image('segunda.jpg'))->assertSet('coverReady', false)
            ->assertSet('positioning', true)->assertSet('positionX', 50)->assertSet('positionY', 50)
            ->call('createWorkspace')->assertHasErrors('cover');
        $this->assertDatabaseCount('workspaces', 0);
    }
}
