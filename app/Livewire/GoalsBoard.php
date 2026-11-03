<?php

namespace App\Livewire;

use App\Enums\GoalCategory;
use App\Enums\GoalDisplay;
use App\Enums\GoalPeriod;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Models\Workspace;
use App\Services\GoalDescription;
use App\Services\ManageGoals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GoalsBoard extends Component
{
    use WithPagination;

    #[Locked]
    public int $workspaceId;

    #[Locked]
    public string $period = GoalPeriod::Annual->value;

    #[Url(as: 'view')]
    public string $display = GoalDisplay::Table->value;

    #[Url(as: 'year')]
    public int $yearFilter = 0;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $priorityFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url(as: 'month')]
    public string $monthFilter = '';

    #[Url(as: 'week')]
    public string $weekFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Locked]
    public bool $formOpen = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $formVersion = 0;

    public array $form = [];

    public string $message = '';

    public function mount(int $workspaceId, string $period = GoalPeriod::Annual->value): void
    {
        $this->workspaceId = $workspaceId;
        $this->period = $period;
        $this->yearFilter = $this->yearFilter ?: ($period === GoalPeriod::Weekly->value ? now()->isoWeekYear() : now()->year);
        if ($period === GoalPeriod::Monthly->value) {
            $this->monthFilter = $this->monthFilter ?: (string) now()->month;
        } elseif ($period === GoalPeriod::Weekly->value) {
            $this->weekFilter = $this->weekFilter ?: (string) now()->isoWeek();
        }
        $this->workspace();
    }

    protected function workspace(): Workspace
    {
        abort_unless(auth()->check(), 401);
        $workspace = auth()->user()->workspaces()->findOrFail($this->workspaceId);
        Gate::authorize('goals.view', $workspace);

        return $workspace;
    }

    public function selectDisplay(string $display): void
    {
        validator(['display' => $display], ['display' => Rule::enum(GoalDisplay::class)])->validate();
        $this->display = $display;
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.')) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'priorityFilter', 'categoryFilter', 'dateFrom', 'dateTo');
        $this->resetValidation();
        $this->resetPage();
    }

    private function periodDate(): CarbonImmutable
    {
        validator(['year' => $this->yearFilter, 'month' => $this->monthFilter, 'week' => $this->weekFilter], [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'week' => ['nullable', 'integer', 'between:1,53'],
        ])->validate();

        return match ($this->period) {
            GoalPeriod::Monthly->value => CarbonImmutable::create($this->yearFilter, (int) $this->monthFilter, 1),
            GoalPeriod::Weekly->value => CarbonImmutable::now()->setISODate($this->yearFilter, (int) $this->weekFilter, 1)->startOfDay(),
            default => CarbonImmutable::create($this->yearFilter, 1, 1),
        };
    }

    public function navigatePeriod(int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $date = $this->periodDate();
        $date = match ($this->period) {
            GoalPeriod::Monthly->value => $date->addMonths($direction),
            GoalPeriod::Weekly->value => $date->addWeeks($direction),
            default => $date->addYears($direction),
        };
        $this->applyPeriodDate($date);
    }

    public function openCurrentPeriod(): void
    {
        $this->applyPeriodDate(CarbonImmutable::now(config('app.timezone')));
    }

    private function applyPeriodDate(CarbonImmutable $date): void
    {
        $year = $this->period === GoalPeriod::Weekly->value ? $date->isoWeekYear() : $date->year;
        abort_unless($year >= 2000 && $year <= 2100, 422);
        $this->yearFilter = $year;
        if ($this->period === GoalPeriod::Monthly->value) {
            $this->monthFilter = (string) $date->month;
        } elseif ($this->period === GoalPeriod::Weekly->value) {
            $this->weekFilter = (string) $date->isoWeek();
        }
        $this->closeForm();
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('goals.manage', $this->workspace());
        $this->editingId = null;
        $this->form = [
            'title' => '', 'description' => '', 'period' => $this->period, 'year' => $this->yearFilter,
            'month' => $this->period === GoalPeriod::Monthly->value ? ($this->monthFilter ?: now()->month) : null,
            'week' => $this->period === GoalPeriod::Weekly->value ? ($this->weekFilter ?: now()->isoWeek()) : null,
            'priority' => GoalPriority::Medium->value, 'category' => '', 'status' => GoalStatus::NotStarted->value, 'due_date' => '',
            'progress_percent' => '0', 'parent_id' => null,
        ];
        $this->showForm();
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('goals.manage', $this->workspace());
        $this->openDetails($id);
    }

    public function openDetails(int $id): void
    {
        $workspace = $this->workspace();
        $query = $workspace->goals()->where('period', $this->period)->where('year', $this->yearFilter);
        if ($this->period === GoalPeriod::Monthly->value) {
            $query->where('month', $this->monthFilter);
        } elseif ($this->period === GoalPeriod::Weekly->value) {
            $query->where('week', $this->weekFilter);
        }
        $goal = $query->findOrFail($id);
        $this->editingId = $goal->id;
        $this->form = Arr::only($goal->toArray(), array_keys(ManageGoals::rules()));
        $this->form['description'] = app(GoalDescription::class)->sanitize($goal->description) ?? '';
        $this->form['due_date'] = $goal->due_date?->toDateString() ?? '';
        $this->showForm();
    }

    private function showForm(): void
    {
        $this->resetValidation();
        $this->formVersion++;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->reset('formOpen', 'editingId', 'form');
        $this->resetValidation();
    }

    public function saveGoal(): void
    {
        $workspace = $this->workspace();
        Gate::authorize('goals.manage', $workspace);
        abort_unless($this->formOpen, 422);
        $this->form['period'] = $this->period;
        $this->form['year'] = $this->yearFilter;
        $this->form['month'] = $this->period === GoalPeriod::Monthly->value ? (int) $this->monthFilter : null;
        $this->form['week'] = $this->period === GoalPeriod::Weekly->value ? (int) $this->weekFilter : null;
        $this->form['title'] = trim((string) ($this->form['title'] ?? ''));
        $this->validate(collect(ManageGoals::rules())->mapWithKeys(fn ($rules, $key) => ['form.'.$key => array_map(
            fn ($rule) => is_string($rule) ? str_replace('required_if:period,', 'required_if:form.period,', $rule) : $rule,
            $rules,
        )])->all());
        try {
            $goal = app(ManageGoals::class)->save($workspace, $this->form, $this->editingId);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('form.'.$key, $message);
                }
            }

            return;
        }
        $this->yearFilter = $goal->year;
        $this->message = $this->editingId ? 'Meta atualizada.' : 'Meta criada.';
        $this->closeForm();
        $this->resetPage();
    }

    public function changeStatus(int $id, string $status): void
    {
        app(ManageGoals::class)->changeStatus($this->workspace(), $id, $status);
        $this->message = 'Status atualizado.';
    }

    public function changePriority(int $id, string $priority): void
    {
        app(ManageGoals::class)->changePriority($this->workspace(), $id, $priority);
        $this->message = 'Prioridade atualizada.';
    }

    public function changeCategory(int $id, ?string $category): void
    {
        app(ManageGoals::class)->changeCategory($this->workspace(), $id, $category);
        $this->message = 'Categoria atualizada.';
    }

    public function changeProgress(int $id, mixed $progress): void
    {
        app(ManageGoals::class)->changeProgress($this->workspace(), $id, $progress);
        $this->message = 'Progresso atualizado.';
    }

    public function deleteGoal(int $id): void
    {
        app(ManageGoals::class)->delete($this->workspace(), $id);
        if ($this->editingId === $id) {
            $this->closeForm();
        }
        $this->message = 'Meta excluída.';
        $this->resetPage();
    }

    private function filteredGoals(Workspace $workspace): Builder
    {
        validator($this->only(['period', 'display', 'yearFilter']), [
            'period' => ['required', Rule::enum(GoalPeriod::class)],
            'display' => ['required', Rule::enum(GoalDisplay::class)],
            'yearFilter' => ['required', 'integer', 'between:2000,2100'],
        ])->validate();
        $query = $workspace->goals()->getQuery()->with('parent')->where('period', $this->period)->where('year', $this->yearFilter);
        $dateValidation = validator($this->only(['dateFrom', 'dateTo']), [
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', ...($this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : [])],
        ], attributes: ['dateFrom' => 'prazo inicial', 'dateTo' => 'prazo final']);
        $this->resetValidation(['dateFrom', 'dateTo']);
        if ($dateValidation->fails()) {
            foreach ($dateValidation->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return $query->whereRaw('1 = 0');
        }
        if (trim($this->search) !== '') {
            $search = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $search));
        }
        foreach (['statusFilter' => 'status', 'priorityFilter' => 'priority', 'categoryFilter' => 'category'] as $property => $column) {
            if ($this->$property !== '') {
                $this->$property === '__empty__' ? $query->whereNull($column) : $query->where($column, $this->$property);
            }
        }
        if ($this->period === GoalPeriod::Monthly->value && $this->monthFilter !== '') {
            $query->where('month', $this->monthFilter);
        }
        if ($this->period === GoalPeriod::Weekly->value && $this->weekFilter !== '') {
            $query->where('week', $this->weekFilter);
        }
        if ($this->dateFrom !== '') {
            $query->whereDate('due_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('due_date', '<=', $this->dateTo);
        }

        return $query->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', GoalPriority::values())
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id');
    }

    public function render()
    {
        $workspace = $this->workspace();
        $periodDate = $this->periodDate();
        $timeLabel = match ($this->period) {
            GoalPeriod::Monthly->value => ucfirst($periodDate->locale('pt_BR')->translatedFormat('F \\d\\e Y')),
            GoalPeriod::Weekly->value => 'Semana '.$this->weekFilter.' de '.$this->yearFilter,
            default => (string) $this->yearFilter,
        };
        $query = $this->filteredGoals($workspace);
        $total = (clone $query)->count();
        $goals = $this->display === GoalDisplay::Table->value ? $query->paginate(15) : $query->get();
        $categoryOptions = collect(GoalCategory::values())->sort()->values();
        $parentOptions = collect();
        if ($this->formOpen && $this->period !== GoalPeriod::Annual->value) {
            $periods = $this->period === GoalPeriod::Weekly->value ? [GoalPeriod::Annual->value, GoalPeriod::Monthly->value] : [GoalPeriod::Annual->value];
            $parentOptions = $workspace->goals()->whereIn('period', $periods)->orderByDesc('year')->orderBy('title')->get();
        }

        return view('livewire.goals-board', compact('workspace', 'goals', 'total', 'categoryOptions', 'parentOptions', 'periodDate', 'timeLabel'));
    }
}
