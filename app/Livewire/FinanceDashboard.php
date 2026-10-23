<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\Workspace;
use App\Services\FinancialSummary;
use App\Services\Money;
use App\Services\OpenFinancialMonth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class FinanceDashboard extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Url(history: true)]
    public string $month = '';

    public string $selectedMonth = '';

    public string $weeklyLimitInput = '0';

    public string $reserveInput = '0';

    public string $investmentBalanceInput = '';

    public string $categoryName = '';

    public string $message = '';

    public function mount(int $workspaceId, string $month): void
    {
        $this->workspaceId = $workspaceId;
        $this->month = $month;
        $this->selectedMonth = $month;
        $this->loadBudget();
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
        $budget = $this->workspace()->budgets()->where('month', $this->month)->first();
        $this->weeklyLimitInput = (string) (($budget?->weekly_limit_cents ?? 0) / 100);
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
        if ($workspace->allows(auth()->user(), Permission::FinanceCreate)) {
            $copied = app(OpenFinancialMonth::class)->handle($workspace, $target);
            if ($copied > 0) {
                $this->message = $copied.' lançamento(s) fixo(s) copiado(s) como pendentes.';
            }
        }
        $this->month = $target;
        $this->selectedMonth = $target;
        $this->loadBudget();
        $this->dispatch('financial-month-changed', month: $target);
    }

    public function updatedMonth(): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
        $this->selectedMonth = $this->month;
        $this->loadBudget();
        $this->dispatch('financial-month-changed', month: $this->month);
    }

    public function saveBudget(): void
    {
        $workspace = $this->workspace();
        Gate::authorize(Permission::BudgetManage->value, $workspace);
        $this->validate([
            'month' => ['required', 'date_format:Y-m'],
            'weeklyLimitInput' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'reserveInput' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'investmentBalanceInput' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ]);
        $workspace->budgets()->updateOrCreate(['month' => $this->month], [
            'weekly_limit_cents' => Money::cents($this->weeklyLimitInput),
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

        return view('livewire.finance-dashboard', app(FinancialSummary::class)->forMonth($workspace, $this->month) + ['workspace' => $workspace]);
    }
}
