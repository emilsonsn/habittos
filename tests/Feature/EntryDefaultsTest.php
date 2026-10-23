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
