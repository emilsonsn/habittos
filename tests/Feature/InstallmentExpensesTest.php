<?php

namespace Tests\Feature;

use App\Livewire\EntryForm;
use App\Livewire\FinanceDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\OpenFinancialMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstallmentExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return app(CreateWorkspace::class)->handle($owner, 'Casa');
    }

    public function test_form_defaults_and_editing_preserve_installment_and_weekly_choices(): void
    {
        $workspace = $this->workspace();
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'section' => 'installment-expenses'])
            ->assertSet('form.type', 'expense')->assertSet('form.is_installment', true)->assertSet('form.is_fixed', false)
            ->assertSeeInOrder(['Lançamento fixo', 'Parcelado'])
            ->set('form.description', 'Celular')->set('form.amount', '500')
            ->set('form.counts_toward_weekly_spending', false)
            ->call('saveAndCreateAnother')->assertHasNoErrors()
            ->assertSet('form.is_installment', true)->assertSet('form.counts_toward_weekly_spending', true);
        $entry = $workspace->entries()->firstOrFail();
        $this->assertTrue($entry->is_installment);
        $this->assertFalse($entry->is_fixed);
        $this->assertFalse($entry->counts_toward_weekly_spending);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->assertSet('form.is_installment', true)->assertSet('form.counts_toward_weekly_spending', false)
            ->set('form.description', 'Celular atualizado')->call('save')->assertHasNoErrors();
        $this->assertTrue($entry->fresh()->is_installment);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->assertSet('form.is_installment', false)
            ->set('form.type', 'income')->assertDontSee('wire:model="form.is_installment"', false)
            ->set('form.type', 'investment')->assertDontSee('wire:model="form.is_installment"', false);
    }

    public function test_section_respects_cycle_workspace_and_entry_permissions(): void
    {
        $workspace = $this->workspace();
        $workspace->budgets()->create(['month' => '2026-11', 'starts_on' => '2026-11-03']);
        $entry = $workspace->entries()->create(['description' => 'Celular outubro', 'type' => 'expense', 'is_installment' => true, 'amount_cents' => 50000, 'date' => '2026-11-02']);
        $workspace->entries()->create(['description' => 'Próximo ciclo', 'type' => 'expense', 'is_installment' => true, 'amount_cents' => 50000, 'date' => '2026-11-03']);
        $workspace->entries()->create(['description' => 'Despesa avulsa', 'type' => 'expense', 'amount_cents' => 1000, 'date' => '2026-10-10']);
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $foreign = $other->entries()->create(['description' => 'Outro ambiente', 'type' => 'expense', 'is_installment' => true, 'amount_cents' => 50000, 'date' => '2026-10-10']);

        $this->get(route('finance.installment-expenses', [$workspace, 'month' => '2026-10']))
            ->assertOk()->assertSee('Despesas parceladas do mês')->assertSee('Celular outubro')
            ->assertDontSee('Próximo ciclo')->assertDontSee('Despesa avulsa')->assertDontSee('Outro ambiente')
            ->assertSee('wire:click="editEntry('.$entry->id.')"', false)
            ->assertSee('wire:click="deleteEntry('.$entry->id.')"', false);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-10', 'section' => 'installment-expenses'])
            ->call('togglePaid', $entry->id)->assertHasNoErrors()
            ->call('deleteEntry', $entry->id)->assertHasNoErrors()->assertSee('Nenhuma despesa parcelada neste mês.')
            ->call('openMonth', '2026-11')->assertSet('section', 'installment-expenses')->assertSee('Próximo ciclo');
        $this->assertModelMissing($entry);
        $this->assertModelExists($foreign);

        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.view']]);
        $this->actingAs($reader);
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11', 'section' => 'installment-expenses'])
            ->assertSee('Próximo ciclo')->assertDontSee('wire:click="editEntry(', false)
            ->assertDontSee('wire:click="deleteEntry(', false)->call('deleteEntry', $foreign->id)->assertForbidden();
    }

    public function test_sync_copies_installments_and_fixed_entries_once_preserving_flags_and_sources(): void
    {
        $workspace = $this->workspace();
        foreach ([['Celular', 'expense', false, true], ['Ambos', 'expense', true, true], ['Salário', 'income', true, false], ['Avulsa', 'expense', false, false], ['Receita não fixa', 'income', false, true], ['Investimento', 'investment', true, true]] as [$description, $type, $fixed, $installment]) {
            $workspace->entries()->create(['description' => $description, 'type' => $type, 'is_fixed' => $fixed, 'is_installment' => $installment, 'amount_cents' => 50000, 'date' => '2026-10-10', 'paid' => true, 'counts_toward_weekly_spending' => false, 'notes' => 'Preservar']);
        }
        $other = app(CreateWorkspace::class)->handle(auth()->user(), 'Outro');
        $other->entries()->create(['description' => 'Privada', 'type' => 'expense', 'is_installment' => true, 'amount_cents' => 1000, 'date' => '2026-10-10']);
        $originals = $workspace->entries()->orderBy('id')->get()->toArray();
        Livewire::test(FinanceDashboard::class, ['workspaceId' => $workspace->id, 'month' => '2026-11', 'section' => 'installment-expenses'])
            ->call('startSynchronization')->set('cycleStartInput', '2026-11-03')
            ->call('synchronizePreviousMonth')->assertHasNoErrors()->assertSee('3 lançamento(s)')
            ->assertSee('Celular')->assertSee('Ambos')->assertDontSee('Privada');
        $copies = $workspace->entries()->whereDate('date', '2026-11-03')->get();
        $this->assertCount(3, $copies);
        $this->assertFalse($copies->firstWhere('description', 'Celular')->is_fixed);
        $this->assertTrue($copies->firstWhere('description', 'Celular')->is_installment);
        $this->assertTrue($copies->firstWhere('description', 'Ambos')->is_fixed);
        foreach ($copies as $copy) {
            $this->assertFalse($copy->paid);
            $this->assertFalse($copy->counts_toward_weekly_spending);
            $this->assertSame('Preservar', $copy->notes);
        }
        $this->assertSame($originals, $workspace->entries()->whereDate('date', '2026-10-10')->orderBy('id')->get()->toArray());
        $copies->firstWhere('description', 'Celular')->update(['is_installment' => false]);
        $this->assertSame(2, app(OpenFinancialMonth::class)->handle($workspace, '2026-12', '2026-12-01', '2026-12-31'));
        $this->assertDatabaseMissing('entries', ['workspace_id' => $workspace->id, 'description' => 'Celular', 'date' => '2026-12-01 00:00:00']);
    }

    public function test_http_and_livewire_keep_installment_flag_exclusive_to_expenses(): void
    {
        $workspace = $this->workspace();
        $this->post(route('entries.store', $workspace), ['description' => 'Celular HTTP', 'amount' => '500', 'type' => 'expense', 'is_installment' => true])
            ->assertSessionHasNoErrors()->assertRedirect();
        $entry = $workspace->entries()->firstOrFail();
        $this->assertTrue($entry->is_installment);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->set('form.type', 'income')->call('save')->assertHasNoErrors();
        $this->assertFalse($entry->fresh()->is_installment);
        $this->post(route('entries.store', $workspace), ['description' => 'Investimento HTTP', 'amount' => '500', 'type' => 'investment', 'is_installment' => true])
            ->assertSessionHasNoErrors();
        $this->assertFalse($workspace->entries()->where('type', 'investment')->firstOrFail()->is_installment);
    }
}
