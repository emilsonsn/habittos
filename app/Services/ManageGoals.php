<?php

namespace App\Services;

use App\Enums\GoalCategory;
use App\Enums\GoalPeriod;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageGoals
{
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'period' => ['required', Rule::enum(GoalPeriod::class)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'required_if:period,'.GoalPeriod::Monthly->value, 'integer', 'between:1,12'],
            'week' => ['nullable', 'required_if:period,'.GoalPeriod::Weekly->value, 'integer', 'between:1,53'],
            'priority' => ['required', Rule::enum(GoalPriority::class)],
            'status' => ['required', Rule::enum(GoalStatus::class)],
            'category' => ['nullable', Rule::enum(GoalCategory::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'progress_percent' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    protected function authorize(Workspace $workspace): void
    {
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize('goals.manage', $workspace);
    }

    public function save(Workspace $workspace, array $input, ?int $id = null): Goal
    {
        $this->authorize($workspace);

        return DB::transaction(function () use ($workspace, $input, $id) {
            // Serialize hierarchy changes and deletion inside this workspace.
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $goal = $id ? $workspace->goals()->findOrFail($id) : null;
            foreach (['title', 'category'] as $key) {
                if (isset($input[$key]) && is_string($input[$key])) {
                    $input[$key] = trim($input[$key]);
                }
            }
            $data = validator($input, self::rules(), attributes: [
                'title' => 'nome da meta', 'year' => 'ano', 'month' => 'mês', 'week' => 'semana',
                'progress_percent' => 'feito (%)', 'parent_id' => 'meta maior',
            ])->validate();
            if ($data['status'] === GoalStatus::Completed->value) {
                $data['progress_percent'] = 100;
            }
            if (array_key_exists('description', $data)) {
                $data['description'] = app(GoalDescription::class)->sanitize($data['description']);
            }
            $data['month'] = $data['period'] === GoalPeriod::Monthly->value ? $data['month'] : null;
            $data['week'] = $data['period'] === GoalPeriod::Weekly->value ? $data['week'] : null;
            if ($data['period'] === GoalPeriod::Weekly->value) {
                $date = CarbonImmutable::now()->setISODate((int) $data['year'], (int) $data['week'], 1);
                if ($date->isoWeekYear() !== (int) $data['year']) {
                    throw ValidationException::withMessages(['week' => 'Esta semana não existe no ano informado.']);
                }
            }
            $parentId = $data['parent_id'] ?? null;
            if ($parentId) {
                $parent = $workspace->goals()->findOrFail($parentId);
                if ($parent->id === $goal?->id || GoalPeriod::from($parent->period)->rank() >= GoalPeriod::from($data['period'])->rank()) {
                    throw ValidationException::withMessages(['parent_id' => 'Escolha uma meta de um período maior deste ambiente.']);
                }
            }
            if ($goal && $goal->children()->get()->contains(fn ($child) => GoalPeriod::from($child->period)->rank() <= GoalPeriod::from($data['period'])->rank())) {
                throw ValidationException::withMessages(['period' => 'Desvincule as metas menores antes de alterar este período.']);
            }
            foreach (['category', 'due_date', 'parent_id'] as $key) {
                $data[$key] = ($data[$key] ?? '') === '' ? null : $data[$key];
            }
            if ($goal) {
                $goal->update($data);

                return $goal->fresh();
            }

            return $workspace->goals()->create($data);
        });
    }

    public function changeStatus(Workspace $workspace, int $id, string $status): void
    {
        $this->authorize($workspace);
        validator(['status' => $status], ['status' => ['required', Rule::enum(GoalStatus::class)]])->validate();
        $data = ['status' => $status];
        if ($status === GoalStatus::Completed->value) {
            $data['progress_percent'] = 100;
        }
        $workspace->goals()->findOrFail($id)->update($data);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        DB::transaction(function () use ($workspace, $id) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $workspace->goals()->findOrFail($id)->delete();
        });
    }

}
