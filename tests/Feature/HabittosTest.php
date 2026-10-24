<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreateWorkspace;
use App\Services\FinancialSummary;
use App\Services\OpenFinancialMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabittosTest extends TestCase
{
    use RefreshDatabase;

    private function environment(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Casa');

        return [$owner, $workspace];
    }

    public function test_registration_creates_a_private_environment_and_hashes_password(): void
    {
        $this->post('/cadastro', ['name' => 'Pessoa', 'email' => 'person@example.com', 'password' => 'password1234', 'password_confirmation' => 'password1234'])->assertRedirect('/home');
        $user = User::first();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('password1234', $user->password);
        $this->assertCount(1, $user->workspaces);
        $this->assertDatabaseCount('categories', 8);
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password1234']);
        $this->post('/entrar', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/entrar', ['email' => $user->email, 'password' => 'password1234'])->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
        $this->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_private_routes_require_authentication(): void
    {
        $this->get('/ambientes')->assertRedirect('/entrar');
    }

    public function test_foreign_workspace_cannot_be_read(): void
    {
        [, $workspace] = $this->environment();
        $this->actingAs(User::factory()->create())->get(route('finance.index', $workspace))->assertNotFound();
    }

    public function test_read_only_member_cannot_write_even_by_direct_request(): void
    {
        [, $workspace] = $this->environment();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader)->get(route('finance.index', $workspace))->assertOk()->assertDontSee('Novo lançamento');
        $this->post(route('entries.store', $workspace), [])->assertForbidden();
        $this->put(route('budget.update', $workspace), [])->assertForbidden();
        $this->get(route('members.index', $workspace))->assertForbidden();
    }

    public function test_revoked_permissions_take_effect_on_next_request(): void
    {
        [$owner, $workspace] = $this->environment();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader)->get(route('finance.index', $workspace))->assertOk();
        $this->actingAs($owner)->post(route('members.store', $workspace), ['email' => $reader->email, 'permissions' => []])->assertSessionHasNoErrors();
        $this->actingAs($reader)->get(route('finance.index', $workspace))->assertForbidden();
        $this->actingAs($owner)->delete(route('members.destroy', [$workspace, $reader]))->assertRedirect();
        $this->actingAs($reader)->get(route('finance.index', $workspace))->assertNotFound();
    }

    public function test_administrator_cannot_remove_owner_or_delegate_administration(): void
    {
        [$owner, $workspace] = $this->environment();
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $workspace->members()->attach($admin, ['permissions' => ['members.manage']]);
        $this->actingAs($admin)->delete(route('members.destroy', [$workspace, $owner]))->assertStatus(422);
        $this->post(route('members.store', $workspace), ['email' => $other->email, 'permissions' => ['members.manage']])->assertForbidden();
        $this->post(route('members.store', $workspace), ['email' => $admin->email, 'permissions' => ['finance.view']])->assertForbidden();
    }

    public function test_foreign_category_and_foreign_entry_are_rejected(): void
    {
        [$owner, $workspace] = $this->environment();
        $foreign = app(CreateWorkspace::class)->handle($owner, 'Outro');
        $entry = $foreign->entries()->create(['description' => 'Privado', 'type' => 'expense', 'amount_cents' => 1200, 'date' => '2026-10-01']);
        $this->actingAs($owner)->post(route('entries.store', $workspace), ['description' => 'Teste', 'type' => 'expense', 'amount' => '12.00', 'date' => '2026-10-01', 'category_id' => $foreign->categories()->first()->id])->assertSessionHasErrors('category_id');
        $this->get(route('entries.edit', [$workspace, $entry]))->assertNotFound();
        $this->put(route('entries.update', [$workspace, $entry]), ['description' => 'Alterado', 'type' => 'expense', 'amount' => '1.00', 'date' => '2026-10-01'])->assertNotFound();
        $this->delete(route('entries.destroy', [$workspace, $entry]))->assertNotFound();
        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'description' => 'Privado']);
    }

    public function test_entry_crud_preserves_cents_and_checkbox_changes(): void
    {
        [$owner, $workspace] = $this->environment();
        $this->actingAs($owner)->post(route('entries.store', $workspace), ['description' => 'Mercado', 'type' => 'expense', 'amount' => '19.99', 'date' => '2026-10-22', 'paid' => '1', 'is_fixed' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $entry = $workspace->entries()->first();
        $this->assertSame(1999, $entry->amount_cents);
        $this->put(route('entries.update', [$workspace, $entry]), ['description' => 'Mercado novo', 'type' => 'expense', 'amount' => '20.10', 'date' => '2026-10-23'])->assertRedirect();
        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'amount_cents' => 2010, 'paid' => false, 'is_fixed' => false]);
        $this->delete(route('entries.destroy', [$workspace, $entry]))->assertRedirect();
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_invalid_money_and_period_are_rejected(): void
    {
        [$owner, $workspace] = $this->environment();
        $this->actingAs($owner)->post(route('entries.store', $workspace), ['description' => 'Teste', 'type' => 'expense', 'amount' => '1.001', 'date' => '2026-10-22'])->assertSessionHasErrors('amount');
        $this->get(route('finance.index', [$workspace, 'month' => '2026-99']))->assertSessionHasErrors('month');
    }

    public function test_summary_distinguishes_settled_and_planned_and_counts_last_day(): void
    {
        [, $workspace] = $this->environment();
        foreach ([['income', 100000, true, false, '2026-10-01'], ['income', 20000, false, false, '2026-10-02'], ['expense', 10000, true, true, '2026-10-03'], ['expense', 2000, false, false, '2026-10-31'], ['investment', 5000, true, false, '2026-10-15'], ['expense', 9999, true, false, '2026-11-01']] as [$type, $amount, $paid, $fixed, $date]) {
            $workspace->entries()->create(['description' => 'Teste', 'type' => $type, 'amount_cents' => $amount, 'paid' => $paid, 'is_fixed' => $fixed, 'date' => $date]);
        }
        $workspace->budgets()->create(['month' => '2026-10', 'reserve_cents' => 3000, 'weekly_limit_cents' => 10000]);
        $summary = app(FinancialSummary::class)->forMonth($workspace, '2026-10');
        $this->assertSame(90000, $summary['balance']);
        $this->assertSame(87000, $summary['available']);
        $this->assertSame(105000, $summary['projected']);
        $this->assertSame(2000, $summary['pending']);
        $this->assertCount(4, $summary['weeks']);
        $this->assertSame(2000, $summary['weeks']->last()['spent']);
        $this->assertSame(2000, $summary['weeks']->sum('spent'));
    }

    public function test_goals_are_scoped_and_permission_protected(): void
    {
        [$owner, $workspace] = $this->environment();
        $other = app(CreateWorkspace::class)->handle($owner, 'Outro');
        $goal = $other->goals()->create(['title' => 'Viagem', 'period' => 'annual', 'year' => 2026, 'target_cents' => 50000]);
        $this->actingAs($owner)->put(route('goals.update', [$workspace, $goal]), ['current' => '10.00'])->assertNotFound();
        $this->post(route('goals.store', $workspace), ['title' => 'Reserva', 'period' => 'monthly', 'year' => 2026, 'target' => '500'])->assertSessionHasErrors('month');
        $this->post(route('goals.store', $workspace), ['title' => 'Reserva', 'period' => 'annual', 'year' => 2026, 'target' => '500'])->assertSessionHasNoErrors();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['goals.view']]);
        $this->actingAs($reader)->get(route('goals.index', $workspace))->assertOk();
        $this->post(route('goals.store', $workspace), [])->assertForbidden();
    }

    public function test_pages_render_for_owner(): void
    {
        [$owner, $workspace] = $this->environment();
        $this->actingAs($owner);
        foreach (['home' => [], 'finance.index' => [$workspace], 'members.index' => [$workspace], 'goals.index' => [$workspace]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk();
        }
    }

    public function test_explicit_month_sync_copies_fixed_expenses_and_preserves_history(): void
    {
        [$owner, $workspace] = $this->environment();
        $expense = $workspace->entries()->create(['description' => 'Aluguel', 'type' => 'expense', 'amount_cents' => 100000, 'date' => '2026-12-31', 'paid' => true, 'is_fixed' => true, 'notes' => 'Contrato']);
        $workspace->entries()->create(['description' => 'Passeio', 'type' => 'expense', 'amount_cents' => 5000, 'date' => '2026-12-15', 'paid' => true, 'is_fixed' => false]);
        $this->actingAs($owner)->post(route('finance.open-month', $workspace), ['month' => '2027-01', 'cycleStartInput' => '2027-01-01', 'cycleEndInput' => '2027-01-31'])->assertRedirect(route('finance.index', [$workspace, 'month' => '2027-01']));
        $copy = $workspace->entries()->where('id', '!=', $expense->id)->where('is_fixed', true)->firstOrFail();
        $this->assertTrue($copy->paid);
        $this->assertSame('2027-01-01', $copy->date->format('Y-m-d'));
        $this->assertSame(100000, $copy->amount_cents);
        $this->assertSame('Contrato', $copy->notes);
        $this->assertDatabaseCount('entries', 3);
        $this->post(route('finance.open-month', $workspace), ['month' => '2027-01', 'cycleStartInput' => '2027-01-01', 'cycleEndInput' => '2027-01-31'])->assertSessionHasErrors('synchronization');
        $this->assertDatabaseCount('entries', 3);
        $this->put(route('entries.update', [$workspace, $copy]), ['description' => 'Aluguel reajustado', 'type' => 'expense', 'amount' => '1200.00', 'date' => '2027-01-31', 'is_fixed' => '1'])->assertRedirect();
        $this->assertSame(100000, $expense->fresh()->amount_cents);
        $this->assertTrue($expense->fresh()->paid);
        $this->post(route('finance.open-month', $workspace), ['month' => '2027-02', 'cycleStartInput' => '2027-02-01', 'cycleEndInput' => '2027-02-28'])->assertRedirect();
        $february = $workspace->entries()->where('description', 'Aluguel reajustado')->orderByDesc('id')->firstOrFail();
        $this->assertSame('2027-02-01', $february->date->format('Y-m-d'));
        $this->assertSame(120000, $february->amount_cents);
        $this->delete(route('entries.destroy', [$workspace, $february]))->assertRedirect();
        $this->post(route('finance.open-month', $workspace), ['month' => '2027-02', 'cycleStartInput' => '2027-02-01', 'cycleEndInput' => '2027-02-28'])->assertRedirect();
        $this->assertNull($february->fresh());
        $this->assertSame(1, $workspace->entries()->where('date', '>=', '2027-02-01')->where('date', '<', '2027-03-01')->count());
        $this->assertNotNull($expense->fresh());
        $this->assertNotNull($copy->fresh());
    }

    public function test_month_opening_requires_write_permission_and_get_is_read_only(): void
    {
        [, $workspace] = $this->environment();
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader)->get(route('finance.index', [$workspace, 'month' => '2027-01']))->assertOk();
        $this->assertDatabaseCount('financial_months', 0);
        $this->post(route('finance.open-month', $workspace), ['month' => '2027-01'])->assertForbidden();
    }

    public function test_month_initialization_is_scoped_and_handles_leap_year_and_missing_previous_month(): void
    {
        [$owner, $workspace] = $this->environment();
        $other = app(CreateWorkspace::class)->handle($owner, 'Outro');
        foreach ([$workspace, $other] as $space) {
            $space->entries()->create(['description' => 'Fixo', 'type' => 'expense', 'amount_cents' => 100, 'date' => '2028-01-31', 'is_fixed' => true]);
        }
        $opener = app(OpenFinancialMonth::class);
        $this->assertSame(1, $opener->handle($workspace, '2028-02', '2028-02-01', '2028-02-29'));
        $this->assertSame('2028-02-01', $workspace->entries()->orderByDesc('id')->first()->date->format('Y-m-d'));
        $this->assertSame(1, $other->entries()->count());
        $this->assertSame(0, $opener->handle($workspace, '2028-04', '2028-04-01', '2028-04-30'));
        $this->assertSame(2, $workspace->entries()->count());
    }

    public function test_new_entry_defaults_to_today_and_budget_is_independent(): void
    {
        [$owner, $workspace] = $this->environment();
        $this->actingAs($owner)->followingRedirects()->get(route('entries.create', [$workspace, 'month' => '2027-02']))->assertOk()->assertSee('Novo lançamento')->assertSee(now()->toDateString());
        foreach (['2026-12' => '200', '2027-01' => '300'] as $month => $limit) {
            $this->put(route('budget.update', $workspace), ['month' => $month, 'weekly_limit' => $limit, 'reserve' => '50'])->assertRedirect();
        }
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2026-12', 'reserve_cents' => 5000, 'weekly_limit_cents' => 0]);
        $this->assertDatabaseHas('budgets', ['workspace_id' => $workspace->id, 'month' => '2027-01', 'reserve_cents' => 5000, 'weekly_limit_cents' => 0]);
    }
}
