<?php

namespace App\Livewire;

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
        $this->form = [
            'description' => $entry?->description ?? '',
            'type' => $entry?->type ?? 'expense',
            'amount' => $entry ? (string) ($entry->amount_cents / 100) : '',
            'date' => $entry?->date->format('Y-m-d') ?? now()->toDateString(),
            'category_id' => $entry?->category_id,
            'paid' => $entry?->paid ?? true,
            'is_fixed' => $entry?->is_fixed ?? false,
            'notes' => $entry?->notes,
        ];
    }

    public function save(): void
    {
        $workspace = $this->workspace();
        if (($this->form['category_id'] ?? null) === '') {
            $this->form['category_id'] = null;
        }
        $rules = collect(EntryRules::forWorkspace($workspace->id))->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $rule])->all();
        $validated = $this->validate($rules)['form'];
        $validated['amount_cents'] = Money::cents($validated['amount']);
        unset($validated['amount']);
        $entry = app(SaveEntry::class)->handle($workspace, $validated, $this->entryId);
        session()->flash('success', $this->entryId ? 'Lançamento atualizado.' : 'Lançamento registrado.');
        $this->redirect(route('finance.index', [$workspace, 'month' => $entry->date->format('Y-m')]), navigate: true);
    }

    public function render()
    {
        $workspace = $this->workspace();

        return view('livewire.entry-form', ['workspace' => $workspace, 'categories' => $workspace->categories()->orderBy('name')->get()]);
    }
}
