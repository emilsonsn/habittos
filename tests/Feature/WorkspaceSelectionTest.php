<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_changes_financial_data_and_preserves_month(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $second = app(CreateWorkspace::class)->handle($user, 'Casa');
        $first->entries()->create(['description' => 'Despesa pessoal exclusiva', 'type' => 'expense', 'amount_cents' => 1000, 'date' => '2027-02-01']);
        $second->entries()->create(['description' => 'Despesa da casa exclusiva', 'type' => 'expense', 'amount_cents' => 2000, 'date' => '2027-02-01']);

        $this->actingAs($user)->get(route('finance.entries', [$first, 'month' => '2027-02']))
            ->assertSee('Despesa pessoal exclusiva')->assertDontSee('Despesa da casa exclusiva');
        $this->post(route('workspaces.select'), ['workspace_id' => $second->id, 'module' => 'finance', 'month' => '2027-02'])
            ->assertRedirect(route('finance.index', [$second, 'month' => '2027-02']))
            ->assertSessionHas('active_workspace_id', $second->id);
        $this->get(route('finance.entries', [$second, 'month' => '2027-02']))
            ->assertSee('Despesa da casa exclusiva')->assertDontSee('Despesa pessoal exclusiva');
        $this->get(route('workspaces.index'))->assertSee('id="workspace-select"', false)
            ->assertSee('aria-selected="true"', false)->assertSee('Casa');

    }

    public function test_switch_keeps_module_and_falls_back_when_permission_is_missing(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Compartilhado');
        $workspace->members()->attach($user, ['permissions' => ['goals.view']]);

        $this->actingAs($user)->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'goals'])
            ->assertRedirect(route('goals.index', $workspace));
        $this->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'finance'])
            ->assertRedirect(route('goals.index', $workspace));
        $this->assertDatabaseCount('financial_months', 0);
    }

    public function test_switch_rejects_foreign_workspace_without_changing_active_selection(): void
    {
        $user = User::factory()->create();
        $own = app(CreateWorkspace::class)->handle($user, 'Meu ambiente');
        $foreign = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Privado');
        $this->actingAs($user)->withSession(['active_workspace_id' => $own->id])
            ->post(route('workspaces.select'), ['workspace_id' => $foreign->id, 'module' => 'finance'])
            ->assertNotFound()->assertSessionHas('active_workspace_id', $own->id);
        $this->get(route('workspaces.index'))->assertDontSee('Privado');
    }

    public function test_revoked_selection_falls_back_and_direct_links_update_selection(): void
    {
        $user = User::factory()->create();
        $own = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $shared = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Casa');
        $shared->members()->attach($user, ['permissions' => ['finance.view']]);
        $this->actingAs($user)->get(route('finance.index', $shared))
            ->assertOk()->assertSessionHas('active_workspace_id', $shared->id);
        $shared->members()->detach($user);
        $this->get(route('workspaces.index'))->assertOk()
            ->assertDontSee('Casa')->assertSessionHas('active_workspace_id', $own->id);
    }

    public function test_empty_membership_and_read_only_switch_do_not_create_financial_records(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Compartilhado');
        $workspace->members()->attach($user, ['permissions' => []]);
        $this->actingAs($user)->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'finance'])
            ->assertRedirect(route('workspaces.index'))->assertSessionHas('active_workspace_id', $workspace->id);
        $workspace->members()->updateExistingPivot($user->id, ['permissions' => ['finance.view']]);
        $this->post(route('workspaces.select'), ['workspace_id' => $workspace->id, 'module' => 'finance', 'month' => '2027-02'])
            ->assertRedirect(route('finance.index', [$workspace, 'month' => '2027-02']));
        $this->assertDatabaseCount('financial_months', 0);
    }
}
