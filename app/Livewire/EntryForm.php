<?php

namespace App\Livewire;

use App\Models\Entry;
use App\Models\Workspace;
use App\Services\EntryRules;
use App\Services\Money;
use App\Services\SaveEntry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EntryForm extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Locked]
    public ?int $entryId = null;

    public array $form = [];

    public ?string $success = null;

    #[Locked]
    public int $formVersion = 0;

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize($this->entryId ? 'finance.update' : 'finance.create', $workspace);

        return $workspace;
    }

    public function mount(int $workspaceId, ?int $entryId = null): void
    {
        $this->workspaceId = $workspaceId;
        $this->entryId = $entryId;
        $workspace = $this->workspace();
        $entry = $entryId ? $workspace->entries()->findOrFail($entryId) : null;
        $this->form = $entry ? [
            'description' => $entry->description,
            'type' => $entry->type,
            'amount' => (string) ($entry->amount_cents / 100),
            'date' => $entry->date->format('Y-m-d'),
            'category_id' => $entry->category_id,
            'paid' => $entry->paid,
            'is_fixed' => $entry->is_fixed,
            'notes' => $entry->notes,
        ] : $this->defaultForm();
    }

    private function defaultForm(): array
    {
        return [
            'description' => '',
            'type' => 'expense',
            'amount' => '',
            'date' => now()->toDateString(),
            'category_id' => null,
            'paid' => true,
            'is_fixed' => false,
            'notes' => null,
        ];
    }

    private function persist(): Entry
    {
        $this->success = null;
        $workspace = $this->workspace();
        if (($this->form['category_id'] ?? null) === '') {
            $this->form['category_id'] = null;
        }
        $rules = collect(EntryRules::forWorkspace($workspace->id))->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $rule])->all();
        $validated = $this->validate($rules)['form'];
        $validated['amount_cents'] = Money::cents($validated['amount']);
        unset($validated['amount']);

        return app(SaveEntry::class)->handle($workspace, $validated, $this->entryId);
    }

    public function save(): void
    {
        $this->persist();
        $this->dispatch('entry-saved', workspaceId: $this->workspaceId);
    }

    public function saveAndCreateAnother(): void
    {
        abort_if($this->entryId !== null, 403);
        $this->persist();
        $this->form = $this->defaultForm();
        $this->resetValidation();
        $this->formVersion++;
        $this->success = 'Lançamento registrado. Você já pode cadastrar o próximo.';
        $this->dispatch('entry-saved', workspaceId: $this->workspaceId, close: false);
    }

    public function cancel(): void
    {
        $this->dispatch('entry-form-cancelled', workspaceId: $this->workspaceId);
    }

    public function render()
    {
        $workspace = $this->workspace();

        return view('livewire.entry-form', ['workspace' => $workspace, 'categories' => $workspace->categories()->orderBy('name')->get()]);
    }
}
