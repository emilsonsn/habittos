<?php

namespace Tests\Feature;

use App\Livewire\EntryForm;
use App\Models\User;
use App\Services\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntryDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_form_creates_a_paid_non_recurring_expense_today_with_null_notes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->assertSet('form.date', '2026-10-22')->assertSet('form.paid', true)
            ->assertSet('form.is_fixed', false)->assertSet('form.notes', null)
            ->set('form.description', 'Mercado')->set('form.amount', '1234.56')->set('form.notes', '   ')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('entries', [
            'workspace_id' => $workspace->id, 'description' => 'Mercado', 'type' => 'expense',
            'amount_cents' => 123456, 'paid' => true, 'is_fixed' => false, 'notes' => null,
        ]);
        $this->assertSame('2026-10-22', $workspace->entries()->first()->date->toDateString());
    }

    public function test_save_and_create_another_resets_all_fields_and_allows_repeated_creation_without_redirecting(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22));
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        $category = $workspace->categories()->firstOrFail();
        $component = Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->assertSee('Salvar e lançar outro')
            ->set('form.description', 'Salário')->set('form.amount', '1234.56')
            ->set('form.type', 'income')->set('form.date', '2026-10-10')
            ->set('form.category_id', $category->id)->set('form.paid', false)
            ->set('form.is_fixed', true)->set('form.notes', 'Detalhes')
            ->call('saveAndCreateAnother')->assertHasNoErrors()->assertNoRedirect()
            ->assertSee('Lançamento registrado. Você já pode cadastrar o próximo.')
            ->assertSet('form', [
                'description' => '', 'type' => 'expense', 'amount' => '', 'date' => '2026-10-22',
                'category_id' => null, 'paid' => true, 'is_fixed' => false, 'notes' => null,
            ])->assertSet('formVersion', 1);
        $this->assertDatabaseHas('entries', [
            'workspace_id' => $workspace->id, 'description' => 'Salário', 'amount_cents' => 123456,
            'type' => 'income', 'category_id' => $category->id, 'paid' => false, 'is_fixed' => true, 'notes' => 'Detalhes',
        ]);
        $component->set('form.description', 'Mercado')->set('form.amount', '19.99')
            ->call('saveAndCreateAnother')->assertHasNoErrors()->assertNoRedirect()
            ->assertSet('form.description', '')->assertSet('formVersion', 2);
        $this->assertSame(2, $workspace->entries()->count());
        $this->assertDatabaseHas('entries', ['workspace_id' => $workspace->id, 'description' => 'Mercado', 'amount_cents' => 1999]);
    }

    public function test_save_and_create_another_preserves_invalid_fields_and_rechecks_permissions(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Mercado')->set('form.amount', 'invalid')
            ->call('saveAndCreateAnother')->assertHasErrors('form.amount')->assertNoRedirect()
            ->assertSet('form.description', 'Mercado')->assertSet('form.amount', 'invalid')
            ->assertSet('formVersion', 0)->assertSet('success', null);
        $reader = User::factory()->create();
        $workspace->members()->attach($reader, ['permissions' => ['finance.create']]);
        $this->actingAs($reader);
        $component = Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id])
            ->set('form.description', 'Mercado')->set('form.amount', '10');
        $workspace->members()->updateExistingPivot($reader->id, ['permissions' => ['finance.view']]);
        $component->call('saveAndCreateAnother')->assertForbidden();
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_save_and_create_another_is_unavailable_when_editing(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $entry = $workspace->entries()->create(['description' => 'Conta', 'type' => 'expense', 'amount_cents' => 5000, 'date' => '2026-10-22']);
        $this->actingAs($user);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->assertDontSee('Salvar e lançar outro')->call('saveAndCreateAnother')->assertForbidden();
        $this->assertDatabaseCount('entries', 1);
    }

    public function test_existing_advanced_data_is_preserved_on_edit(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $entry = $workspace->entries()->create(['description' => 'Conta', 'type' => 'expense', 'amount_cents' => 5000, 'date' => '2026-09-05', 'paid' => false, 'is_fixed' => true, 'notes' => 'Detalhe importante']);
        $this->actingAs($user);
        Livewire::test(EntryForm::class, ['workspaceId' => $workspace->id, 'entryId' => $entry->id])
            ->assertSet('form.date', '2026-09-05')->assertSet('form.paid', false)
            ->assertSet('form.is_fixed', true)->assertSet('form.notes', 'Detalhe importante')
            ->set('form.description', 'Conta atualizada')->call('save')->assertHasNoErrors();
        $this->assertFalse($entry->fresh()->paid);
        $this->assertTrue($entry->fresh()->is_fixed);
        $this->assertSame('Detalhe importante', $entry->fresh()->notes);
    }

    public function test_http_creation_also_accepts_only_basic_data_and_applies_defaults(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($user, 'Casa');
        $this->actingAs($user)->post(route('entries.store', $workspace), ['description' => 'Mercado', 'amount' => '29.99'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $entry = $workspace->entries()->firstOrFail();
        $this->assertTrue($entry->paid);
        $this->assertFalse($entry->is_fixed);
        $this->assertNull($entry->notes);
        $this->assertSame(now()->toDateString(), $entry->date->toDateString());
    }
}
