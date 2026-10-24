<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Livewire\EntryForm;
use App\Livewire\FinanceDashboard;
use App\Livewire\GoalsBoard;
use App\Livewire\MembersManager;
use App\Livewire\WorkspaceSettings;
use App\Livewire\WorkspaceSwitcher;
use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class InteractiveFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_creates_and_selects_an_environment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(WorkspaceSwitcher::class)
            ->set('name', 'Nossa casa')->call('createWorkspace')->assertHasNoErrors()->assertRedirect();
        $workspace = $user->workspaces()->firstOrFail();
        $this->assertSame('Nossa casa', $workspace->name);
        $this->assertSame($workspace->id, session('active_workspace_id'));
        $this->assertCount(8, $workspace->categories);
    }

    public function test_switcher_cannot_select_an_environment_without_membership(): void
    {
        $user = User::factory()->create();
        $foreign = app(CreateWorkspace::class)->handle(User::factory()->create(), 'Privado');
        $this->actingAs($user);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(WorkspaceSwitcher::class)->call('selectWorkspace', $foreign->id);
    }

    public function test_finance_reacts_to_month_changes_and_keeps_fixed_records_independent(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $original = $workspace->entries()->create(['description' => 'Internet', 'type' => 'expense', 'amount_cents' => 9990, 'date' => '2026-12-31', 'paid' => true, 'is_fixed' => true]);
        $this->actingAs($user);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-12'])
            ->call('openMonth', '2027-01')->assertSet('month', '2027-01')->assertDontSee('Internet')
            ->assertDispatched('financial-month-changed', month: '2027-01')
            ->call('startSynchronization')->call('synchronizePreviousMonth')->assertHasNoErrors();
        $this->assertDatabaseCount('entries', 2);
        $copy = $workspace->entries()->where('id', '!=', $original->id)->firstOrFail();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2027-01'])
            ->call('togglePaid', $copy->id)->assertHasNoErrors();
        $this->assertFalse($copy->fresh()->paid);
        $this->assertTrue($original->fresh()->paid);
    }

    public function test_financial_actions_recheck_permissions_and_scope(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('saveBudget')->assertForbidden();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('deleteEntry', 999)->assertForbidden();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('openMonth', '2026-11');
        $this->assertDatabaseCount('financial_months', 0);

        $other = app(CreateWorkspace::class)->handle($owner, 'Outro');
        $entry = $other->entries()->create(['description' => 'Privado', 'type' => 'expense', 'amount_cents' => 100, 'date' => '2026-10-01']);
        $this->actingAs($owner);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('togglePaid', $entry->id);
    }

    public function test_settings_allow_only_owner_to_rename_or_delete(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['permissions' => ['members.manage']]);
        $this->actingAs($admin);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('name', 'Alterado')->call('save')->assertForbidden();
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('confirmation', 'Casa')->call('deleteWorkspace')->assertForbidden();
        $this->actingAs($owner);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('name', 'Nossa casa')->call('save')->assertRedirect(route('settings.index', $workspace));
        $this->assertSame('Nossa casa', $workspace->fresh()->name);
    }

    public function test_environment_deletion_requires_exact_confirmation_and_preserves_other_environments_and_users(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $other = app(CreateWorkspace::class)->handle($user, 'Casa');
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['permissions' => ['finance.view']]);
        $workspace->entries()->create(['description' => 'Compra', 'type' => 'expense', 'amount_cents' => 100, 'date' => '2026-10-01']);
        $this->actingAs($user);
        Livewire::test(WorkspaceSettings::class, ['workspaceId' => $workspace->id])
            ->set('confirmation', 'Errado')->call('deleteWorkspace')->assertHasErrors('confirmation')
            ->set('confirmation', 'Pessoal')->call('deleteWorkspace')->assertRedirect(route('home'));
        $this->assertNull($workspace->fresh());
        $this->assertNotNull($other->fresh());
        $this->assertNotNull($member->fresh());
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseMissing('user_workspace', ['workspace_id' => $workspace->id]);
    }

    public function test_updated_pages_have_no_environment_menu_or_directory(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertDontSee('Seus ambientes</h1>', false)->assertDontSee('<span>Ambientes</span>', false)
            ->assertSee('Criar ambiente')->assertSee('Configurações');
        $this->get(route('settings.index', $workspace))->assertOk()->assertSee('Excluir ambiente');
    }

    public function test_livewire_entry_form_validates_and_saves_cents(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Mercado')->set('form.amount', '19.999')->call('save')->assertHasErrors('form.amount')
            ->set('form.amount', '19.99')->set('form.category_id', '')->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('entry-saved', workspaceId: $workspace->id);
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'amount_cents' => 1999, 'category_id' => null, 'description' => 'Mercado']);
    }

    public function test_livewire_does_not_allow_changing_the_workspace_of_a_component(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])->set('workspaceId', 999);
    }

    public function test_url_history_month_changes_refresh_the_budget_inputs(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $workspace->budgets()->create(['month' => '2026-11', 'weekly_limit_cents' => 12345, 'reserve_cents' => 60000]);
        $this->actingAs($user);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->set('month', '2026-11')->assertSet('selectedMonth', '2026-11')
            ->assertSet('reserveInput', '600')->assertViewHas('weeklyLimit', 0);
        $this->assertDatabaseCount('financial_months', 0);
    }

    public function test_goals_react_to_creation_and_progress_without_page_reload(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Pessoal');
        $this->actingAs($user);
        $component = Livewire::test(GoalsBoard::class, ['workspaceId' => $workspace->id])
            ->set('title', 'Reserva')->set('target', '500.00')->set('period', 'monthly')
            ->call('createGoal')->assertHasErrors('month')->set('month', 11)
            ->call('createGoal')->assertHasNoErrors()->assertSee('Reserva');
        $goal = $workspace->goals()->firstOrFail();
        $component->set('progress.'.$goal->id, '125.25')->call('updateProgress', $goal->id)->assertHasNoErrors();
        $this->assertSame(12525, $goal->fresh()->current_cents);
        $component->call('deleteGoal', $goal->id)->assertHasNoErrors();
        $this->assertNull($goal->fresh());
    }

    public function test_member_management_reacts_and_rechecks_delegated_permissions(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');
        $member = User::factory()->create();
        $this->actingAs($owner);
        $component = Livewire::test(MembersManager::class, ['workspaceId' => $workspace->id])
            ->set('email', $member->email)->set('permissions', ['finance.view'])->call('addMember')->assertHasNoErrors()->assertSee($member->name);
        $this->assertTrue($workspace->allows($member, Permission::FinanceView));
        $component->set('memberPermissions.'.$member->id, [])->call('savePermissions', $member->id)->assertHasNoErrors();
        $this->assertFalse($workspace->allows($member, Permission::FinanceView));
        $workspace->members()->updateExistingPivot($member->id, ['permissions' => ['members.manage']]);
        $this->actingAs($member);
        Livewire::test(MembersManager::class, ['workspaceId' => $workspace->id])
            ->set('email', $owner->email)->call('addMember')->assertStatus(422);
    }
}
