<?php

namespace Tests\Feature;

use App\Livewire\WorkspaceOnboarding;
use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_is_required_until_creation_and_logout_remains_available(): void
    {
        $user = User::factory()->create();
        $this->get(route('onboarding'))->assertRedirect(route('login'));
        $this->actingAs($user);
        foreach (['home', 'workspaces.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('onboarding'));
        }
        $this->post(route('workspaces.store'), ['name' => 'Atalho'])->assertRedirect(route('onboarding'));
        $other = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Privado');
        $this->get(route('finance.entries', $other))->assertRedirect(route('onboarding'));
        $this->get(route('onboarding'))->assertOk()
            ->assertSee('Vamos criar seu primeiro ambiente')->assertSee('Nome do ambiente')->assertSee('Capa do ambiente')
            ->assertDontSee('Navegação principal')->assertDontSee('workspace-select');
        $this->assertCount(0, $user->workspaces);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_resumes_onboarding_instead_of_an_intended_private_url(): void
    {
        $user = User::factory()->create(['password' => 'password1234']);
        $this->withSession(['url.intended' => route('home')])->post(route('login'), ['email' => $user->email, 'password' => 'password1234'])
            ->assertRedirect(route('onboarding'));
        $this->assertCount(0, $user->workspaces);
    }

    public function test_confirmed_creation_saves_name_cover_crop_categories_and_active_workspace_once(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $form = Livewire::test(WorkspaceOnboarding::class)
            ->set('name', '   ')->call('createWorkspace')->assertHasErrors('name')
            ->set('name', str_repeat('x', 101))->call('createWorkspace')->assertHasErrors('name')
            ->set('name', ' Nossa casa ')
            ->set('cover', UploadedFile::fake()->image('capa.jpg'))
            ->call('createWorkspace')->assertHasErrors('cover');
        $this->assertDatabaseCount('workspaces', 0);
        $form->set('positionX', 20)->set('positionY', 80)->call('confirmCoverPosition')->assertHasNoErrors();
        $this->assertDatabaseCount('workspaces', 0);
        $form->call('createWorkspace')->assertHasNoErrors()->assertRedirect(route('home'));
        $workspace = $user->workspaces()->firstOrFail();
        $this->assertSame('Nossa casa', $workspace->name);
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertSame(20, $workspace->cover_position_x);
        $this->assertSame(80, $workspace->cover_position_y);
        $this->assertCount(8, $workspace->categories);
        $this->assertSame($workspace->id, session('active_workspace_id'));
        Storage::disk('local')->assertExists($workspace->cover_path);
        $form->call('createWorkspace')->assertRedirect(route('home'));
        $this->assertDatabaseCount('workspaces', 1);
        $this->get(route('home'))->assertOk()->assertSee('Nossa casa');
        $this->get(route('onboarding'))->assertRedirect(route('home'));
    }

    public function test_cancelled_cover_does_not_create_anything_and_creation_without_cover_is_allowed(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $form = Livewire::test(WorkspaceOnboarding::class)->set('name', 'Pessoal')
            ->set('cover', UploadedFile::fake()->create('arquivo.pdf'))->assertHasErrors('cover');
        $this->assertDatabaseCount('workspaces', 0);
        $form->set('cover', UploadedFile::fake()->image('capa.png'))->call('cancelCoverPosition')
            ->assertSet('cover', null)->assertSet('coverReady', false);
        $this->assertDatabaseCount('workspaces', 0);
        $form->call('createWorkspace')->assertHasNoErrors()->assertRedirect(route('home'));
        $this->assertNull(auth()->user()->workspaces()->firstOrFail()->cover_path);
    }

    public function test_users_with_existing_or_shared_workspace_skip_onboarding_and_cannot_create_another_through_it(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Compartilhado');
        $member = User::factory()->create(['password' => 'password1234']);
        $workspace->members()->attach($member, ['permissions' => ['finance.view']]);
        $this->post(route('login'), ['email' => $member->email, 'password' => 'password1234'])->assertRedirect(route('home'));
        $this->get(route('onboarding'))->assertRedirect(route('home'));
        Livewire::test(WorkspaceOnboarding::class)->assertRedirect(route('home'));
        $this->assertDatabaseCount('workspaces', 1);
    }
}
