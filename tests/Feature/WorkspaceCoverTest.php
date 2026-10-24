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

class WorkspaceCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_dialog_accepts_an_optional_cover_and_home_shows_it_before_indicators(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(WorkspaceSwitcher::class)
            ->set('name', 'Casa')->set('cover', UploadedFile::fake()->image('capa.jpg', 1200, 300))->call('confirmCoverPosition')
            ->call('createWorkspace')->assertHasNoErrors()->assertRedirect();
        $workspace = $user->workspaces()->firstOrFail();
        Storage::disk('local')->assertExists($workspace->cover_path);
        $this->withSession(['active_workspace_id' => $workspace->id])->get(route('home'))->assertOk()
            ->assertSeeInOrder(['class="home-cover"', $workspace->coverUrl(), 'Indicadores do ambiente'], false);
        $this->assertSame(8, $workspace->categories()->count());
        Livewire::test(WorkspaceSwitcher::class)->set('name', 'Sem capa')->call('createWorkspace')->assertHasNoErrors();
        $this->assertNull($user->workspaces()->where('name', 'Sem capa')->firstOrFail()->cover_path);
    }

    public function test_http_creation_supports_cover_and_rejects_non_images_and_large_uploads(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Casa', 'cover' => UploadedFile::fake()->image('capa.png')])
            ->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('local')->assertExists($user->workspaces()->firstOrFail()->cover_path);
        foreach ([UploadedFile::fake()->create('documento.pdf', 10), UploadedFile::fake()->image('grande.jpg')->size(5121)] as $file) {
            $this->post(route('workspaces.store'), ['name' => 'Inválido', 'cover' => $file])->assertSessionHasErrors('cover');
        }
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_settings_replace_and_remove_only_the_selected_workspaces_cover(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa', UploadedFile::fake()->image('original.jpg'));
        $other = app(CreateWorkspace::class)->handle($user, 'Outro', UploadedFile::fake()->image('outro.jpg'));
        $originalPath = $workspace->cover_path;
        $originalUrl = $workspace->coverUrl();
        $this->actingAs($user);
        $component = Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('cover', UploadedFile::fake()->image('nova.webp'))->call('confirmCoverPosition')->call('saveCover')->assertHasNoErrors()
            ->assertSee('Capa do ambiente atualizada.');
        $newPath = $workspace->fresh()->cover_path;
        $this->assertNotSame($originalUrl, $workspace->fresh()->coverUrl());
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertExists($other->cover_path);
        $component->call('removeCover')->assertHasNoErrors()->assertSee('Capa do ambiente removida.');
        $this->assertNull($workspace->fresh()->cover_path);
        Storage::disk('local')->assertMissing($newPath);
        Storage::disk('local')->assertExists($other->cover_path);
        $this->withSession(['active_workspace_id' => $workspace->id])->get(route('home'))->assertOk()->assertDontSee('class="home-cover"', false);
    }

    public function test_invalid_replacement_preserves_the_existing_cover(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa', UploadedFile::fake()->image('original.jpg'));
        $this->actingAs($user);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('cover', UploadedFile::fake()->create('documento.pdf', 10))->call('saveCover')->assertHasErrors('cover');
        $this->assertSame($workspace->cover_path, $workspace->fresh()->cover_path);
        Storage::disk('local')->assertExists($workspace->cover_path);
    }

    public function test_only_members_can_view_the_cover_and_only_the_owner_can_change_it(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa', UploadedFile::fake()->image('original.jpg'));
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['permissions' => []]);
        $this->actingAs($member)->get($workspace->coverUrl())->assertOk()->assertHeader('content-type', 'image/jpeg');
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('cover', UploadedFile::fake()->image('proibida.jpg'))->call('saveCover')->assertForbidden();
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])->call('removeCover')->assertForbidden();
        Storage::disk('local')->assertExists($workspace->cover_path);
        $this->actingAs(User::factory()->create())->get($workspace->coverUrl())->assertNotFound();
        $this->withSession(['active_workspace_id' => $workspace->id])->get(route('home'))->assertOk()->assertDontSee($workspace->coverUrl(), false);
    }

    public function test_deleting_a_workspace_removes_its_cover_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa', UploadedFile::fake()->image('capa.jpg'));
        $this->actingAs($owner);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('confirmation', 'Casa')->call('deleteWorkspace')->assertHasNoErrors()->assertRedirect(route('home'));
        Storage::disk('local')->assertMissing($workspace->cover_path);
    }
}
