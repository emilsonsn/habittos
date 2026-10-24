<?php

namespace App\Livewire;

use App\Enums\FinanceSection;
use App\Enums\Permission;
use App\Models\Workspace;
use App\Services\FinancialCalendar;
use App\Services\FinancialSummary;
use App\Services\Money;
use App\Services\OpenFinancialMonth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class FinanceDashboard extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Url(history: true)]
    public string $month = '';

    #[Locked]
    public string $section = 'dashboard';

    public string $selectedMonth = '';

    public string $reserveInput = '0';

    public string $investmentBalanceInput = '';

    public string $categoryName = '';

    public string $message = '';

    #[Locked]
    public bool $editingCycle = false;

    public string $cycleStartInput = '';

    public string $cycleEndInput = '';

    #[Locked]
    public ?string $synchronizingMonth = null;

    #[Locked]
    public ?string $editingIndicator = null;

    public string $indicatorValue = '';

    #[Locked]
    public bool $entryFormOpen = false;

    #[Locked]
    public ?int $editingEntryId = null;

    #[Locked]
    public int $entryFormVersion = 0;

    public function createEntry(): void
    {
        Gate::authorize(Permission::FinanceCreate->value, $this->workspace());
        $this->editingEntryId = null;
        $this->entryFormVersion++;
        $this->entryFormOpen = true;
    }

    public function editEntry(int $id): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::FinanceUpdate->value, $workspace);
        $this->editingEntryId = $workspace->entries()->findOrFail($id)->id;
        $this->entryFormVersion++;
        $this->entryFormOpen = true;
    }

    public function closeEntryForm(): void
    {
        $this->reset('entryFormOpen', 'editingEntryId');
    }

    #[On('entry-form-cancelled')]
    public function cancelEntryForm(int $workspaceId): void
    {
        if ($workspaceId === $this->workspaceId) {
            $this->closeEntryForm();
        }
    }

    #[On('entry-saved')]
    public function entrySaved(int $workspaceId, bool $close = true): void
    {
        if ($workspaceId !== $this->workspaceId) {
            return;
        }
        $this->workspace();
        $this->message = $this->editingEntryId ? 'Lançamento atualizado.' : 'Lançamento registrado.';
        if ($close) {
            $this->closeEntryForm();
        }
        $this->loadBudget();
    }

    public function startEditingCycle(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $this->resetValidation();
        $this->loadCycleDates($workspace);
        $this->editingCycle = true;
    }

    public function cancelCycle(): void
    {
        $this->reset('editingCycle', 'cycleStartInput', 'cycleEndInput');
        $this->resetValidation();
    }

    public function saveCycle(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        app(FinancialCalendar::class)->savePeriod($workspace, $this->month, $this->cycleStartInput, $this->cycleEndInput);
        $this->cancelCycle();
        $this->message = 'Ciclo atualizado. O ciclo anterior termina na véspera do início e o próximo começa no dia seguinte ao fim informado. As datas dos lançamentos foram preservadas.';
    }

    protected function indicatorColumns(): array
    {
        return [
            'reserve' => 'reserve_cents',
            'investment_balance' => 'investment_balance_cents',
        ];
    }

    public function startEditingIndicator(string $indicator): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $column = $this->indicatorColumns()[$indicator] ?? null;
        abort_unless($column, 422);
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $value = $workspace->budgets()->where('month', $this->month)->value($column);
        $this->resetValidation();
        $this->message = '';
        $this->editingIndicator = $indicator;
        $this->indicatorValue = $value !== null ? number_format($value / 100, 2, '.', '') : '';
    }

    public function cancelIndicator(): void
    {
        $this->reset('editingIndicator', 'indicatorValue');
        $this->resetValidation();
    }

    public function saveIndicator(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $column = $this->indicatorColumns()[$this->editingIndicator] ?? null;
        abort_unless($column, 422);
        $optional = $this->editingIndicator === 'investment_balance';
        $this->validate([
            'month' => ['required', 'date_format:Y-m'],
            'indicatorValue' => [$optional ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ], attributes: ['indicatorValue' => 'valor']);
        $value = $this->indicatorValue !== '' ? Money::cents($this->indicatorValue) : null;
        $workspace->budgets()->updateOrCreate(['month' => $this->month], [$column => $value]);
        $this->cancelIndicator();
        $this->loadBudget();
        $this->message = 'Indicador atualizado.';
    }

    public function mount(int $workspaceId, string $month, string $section = 'dashboard'): void
    {
        $this->section = FinanceSection::from($section)->value;
        $this->workspaceId = $workspaceId;
        $this->month = $month;
        $this->selectedMonth = $month;
        $this->loadBudget();
        if (request()->filled('edit-entry')) {
            $data = request()->validate(['edit-entry' => ['required', 'integer', 'min:1']]);
            $this->editEntry((int) $data['edit-entry']);
        } elseif (request()->boolean('new-entry')) {
            $this->createEntry();
        }
    }

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize(Permission::FinanceView->value, $workspace);

        return $workspace;
    }

    protected function loadBudget(): void
    {
        $this->cancelIndicator();
        $this->cancelCycle();
        $this->reset('synchronizingMonth');
        $budget = $this->workspace()->budgets()->where('month', $this->month)->first();
        $this->reserveInput = (string) (($budget?->reserve_cents ?? 0) / 100);
        $this->investmentBalanceInput = $budget?->investment_balance_cents !== null
            ? (string) ($budget->investment_balance_cents / 100) : '';
    }

    public function openMonth(?string $month = null): void
    {
        $target = $month ?? $this->selectedMonth;
        validator(['month' => $target], ['month' => ['required', 'date_format:Y-m']])->validate();
        $workspace = $this->workspace();
        $this->resetValidation();
        $this->message = '';
        $this->closeEntryForm();
        $this->month = $target;
        $this->selectedMonth = $target;
        $this->loadBudget();
        $this->dispatch('financial-month-changed', month: $target);
    }

    private function loadCycleDates(Workspace $workspace): void
    {
        $period = app(FinancialCalendar::class)->period($workspace, $this->month);
        $this->cycleStartInput = $period['start']->toDateString();
        $this->cycleEndInput = $period['end']->subDay()->toDateString();
    }

    public function startSynchronization(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::FinanceCreate->value, $workspace);
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $summary = app(FinancialSummary::class)->forMonth($workspace, $this->month);
        if ($summary['entries']->isNotEmpty()) {
            throw ValidationException::withMessages(['synchronization' => 'Este mês já possui lançamentos e não pode ser sincronizado.']);
        }
        $this->cancelCycle();
        $this->message = '';
        $this->loadCycleDates($workspace);
        $this->synchronizingMonth = $this->month;
        $this->dispatch('open-month-sync');
    }

    public function cancelSynchronization(): void
    {
        $this->reset('synchronizingMonth', 'cycleStartInput', 'cycleEndInput');
        $this->resetValidation();
    }

    public function synchronizePreviousMonth(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::FinanceCreate->value, $workspace);
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        abort_unless($this->synchronizingMonth === $this->month, 422);
        $this->resetValidation();
        $this->message = '';
        $copied = app(OpenFinancialMonth::class)->handle($workspace, $this->month, $this->cycleStartInput, $this->cycleEndInput);
        $this->cancelSynchronization();
        $this->message = $copied > 0
            ? $copied.' lançamento(s) fixo(s) copiado(s) do mês anterior como efetivados. Ciclo salvo.'
            : 'Ciclo salvo. O mês anterior não possui receitas ou gastos fixos para sincronizar.';
        $this->dispatch('close-month-sync');
    }

    public function updatedMonth(): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $this->selectedMonth = $this->month;
        $this->closeEntryForm();
        $this->loadBudget();
        $this->dispatch('financial-month-changed', month: $this->month);
    }

    public function saveBudget(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->validate([
            'month' => ['required', 'date_format:Y-m'],
            'reserveInput' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'investmentBalanceInput' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ]);
        $workspace->budgets()->updateOrCreate(['month' => $this->month], [
            'reserve_cents' => Money::cents($this->reserveInput),
            'investment_balance_cents' => $this->investmentBalanceInput !== '' ? Money::cents($this->investmentBalanceInput) : null,
        ]);
        $this->message = 'Planejamento salvo.';
    }

    public function saveCategory(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->categoryName = trim($this->categoryName);
        $data = $this->validate(['categoryName' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->where('workspace_id', $workspace->id)]]);
        $workspace->categories()->create(['name' => $data['categoryName']]);
        $this->categoryName = '';
        $this->message = 'Categoria criada.';
    }

    public function deleteEntry(int $id): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::FinanceDelete->value, $workspace);
        $workspace->entries()->findOrFail($id)->delete();
        $this->message = 'Lançamento excluído.';
    }

    public function togglePaid(int $id): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::FinanceUpdate->value, $workspace);
        $entry = $workspace->entries()->findOrFail($id);
        $entry->update(['paid' => ! $entry->paid]);
        $this->message = $entry->paid ? 'Lançamento efetivado.' : 'Lançamento marcado como pendente.';
    }

    public function render()
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $workspace = $this->workspace();

        return view('livewire.finance-dashboard', app(FinancialSummary::class)->forMonth($workspace, $this->month) + ['workspace' => $workspace, 'financeSection' => FinanceSection::from($this->section)]);
    }
}
