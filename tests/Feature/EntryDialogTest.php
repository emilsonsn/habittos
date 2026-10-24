<?php

namespace Tests\Feature;

use App\Livewire\EntryForm;
use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntryDialogTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return app(CreateWorkspace::class)->handle($user, 'Casa');
    }

    public function test_create_and_edit_refresh_the_current_list_without_redirects(): void
    {
        $workspace = $this->workspace();
        $dashboard = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'entries'])
            ->call('createEntry')->assertSet('entryFormOpen', true)->assertSeeLivewire(EntryForm::class);
        $this->assertDatabaseCount('entries', 0);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->assertSet('form.type', 'expense')->assertSeeInOrder(['Despesa', 'Receita', 'Investimento', 'Avançado'])
            ->set('form.description', 'Despesa no diálogo')->set('form.amount', '25.50')->set('form.date', '2026-10-10')
            ->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('entry-saved', workspaceId: $workspace->id);
        $entry = $workspace->entries()->firstOrFail();
        $dashboard->dispatch('entry-saved', workspaceId: $workspace->id)
            ->assertSet('entryFormOpen', false)->assertSet('section', 'entries')->assertSet('month', '2026-10')
            ->assertSee('Despesa no diálogo')->assertSee('R$ 25,50')->assertViewHas('expenses', 2550)
            ->call('editEntry', $entry->id)->assertSet('editingEntryId', $entry->id)->assertSet('entryFormOpen', true);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->assertSet('form.description', 'Despesa no diálogo')->set('form.description', 'Despesa atualizada')
            ->set('form.amount', '30')->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('entry-saved');
        $dashboard->dispatch('entry-saved', workspaceId: $workspace->id)
            ->assertSet('entryFormOpen', false)->assertSee('Despesa atualizada')->assertSee('R$ 30,00')
            ->assertViewHas('expenses', 3000)->assertSee('Lançamento atualizado.');
    }

    public function test_save_another_refreshes_the_table_and_cancel_discards_the_draft(): void
    {
        $workspace = $this->workspace();
        $dashboard = Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'entries'])
            ->call('createEntry');
        $form = Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Primeira')->set('form.amount', '10')->set('form.date', '2026-10-10')
            ->call('saveAndCreateAnother')->assertNoRedirect()->assertHasNoErrors()
            ->assertDispatched('entry-saved', workspaceId: $workspace->id, close: false)->assertSet('form.type', 'expense');
        $dashboard->dispatch('entry-saved', workspaceId: $workspace->id, close: false)
            ->assertSet('entryFormOpen', true)->assertSee('Primeira')->assertViewHas('expenses', 1000);
        $form->set('form.description', 'Rascunho')->call('cancel')
            ->assertDispatched('entry-form-cancelled', workspaceId: $workspace->id);
        $dashboard->dispatch('entry-form-cancelled', workspaceId: $workspace->id)->assertSet('entryFormOpen', false);
        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseMissing('entries', ['description' => 'Rascunho']);
        $dashboard->call('createEntry')->call('openMonth', '2026-11')->assertSet('entryFormOpen', false);
    }

    public function test_validation_preserves_the_form_and_does_not_close_the_dialog(): void
    {
        $workspace = $this->workspace();
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Rascunho')->set('form.amount', 'invalid')
            ->call('save')->assertHasErrors('form.amount')->assertSet('form.description', 'Rascunho')
            ->assertNotDispatched('entry-saved')->assertNoRedirect();
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_dialog_actions_require_permissions_and_recheck_them_when_saving(): void
    {
        $workspace = $this->workspace();
        $entry = $workspace->entries()->create(['description' => 'Existente', 'type' => 'expense', 'amount_cents' => 1000, 'date' => '2026-10-10']);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        foreach (['createEntry' => [], 'editEntry' => [$entry->id]] as $action => $parameters) {
            Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
                ->call($action, ...$parameters)->assertForbidden();
        }
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view', 'finance.update']]);
        $form = Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->set('form.description', 'Proibido');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view']]);
        $form->call('save')->assertForbidden();
        $this->assertSame('Existente', $entry->fresh()->description);
    }

    public function test_edit_dialog_does_not_open_records_from_another_workspace(): void
    {
        $workspace = $this->workspace();
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $entry = $other->entries()->create(['description' => 'Outro ambiente', 'type' => 'income', 'amount_cents' => 1000, 'date' => '2026-10-10']);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10'])
            ->call('editEntry', $entry->id);
    }

    public function test_old_create_and_edit_urls_open_the_dialog_on_the_list(): void
    {
        $workspace = $this->workspace();
        $entry = $workspace->entries()->create(['description' => 'Editar aqui', 'type' => 'income', 'amount_cents' => 1000, 'date' => '2026-10-10']);
        $this->get(route('entries.create', [$workspace, 'month' => '2026-10']))
            ->assertRedirect(route('finance.entries', [$workspace, 'month' => '2026-10', 'new-entry' => 1]));
        $this->followingRedirects()->get(route('entries.edit', [$workspace, $entry]))
            ->assertOk()->assertSee('entry-dialog', false)->assertSee('Editar lançamento')->assertSee('Editar aqui');
    }
}
