<?php

namespace App\Services;

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
            'period' => ['required', Rule::in(array_keys(Goal::PERIODS))],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'required_if:period,monthly', 'integer', 'between:1,12'],
            'week' => ['nullable', 'required_if:period,weekly', 'integer', 'between:1,53'],
            'priority' => ['required', Rule::in(array_keys(Goal::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(Goal::STATUSES))],
            'category' => ['nullable', 'string', 'max:80'],
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
            $data['month'] = $data['period'] === 'monthly' ? $data['month'] : null;
            $data['week'] = $data['period'] === 'weekly' ? $data['week'] : null;
            if ($data['period'] === 'weekly') {
                $date = CarbonImmutable::now()->setISODate((int) $data['year'], (int) $data['week'], 1);
                if ($date->isoWeekYear() !== (int) $data['year']) {
                    throw ValidationException::withMessages(['week' => 'Esta semana não existe no ano informado.']);
                }
            }
            $ranks = ['annual' => 0, 'monthly' => 1, 'weekly' => 2];
            $parentId = $data['parent_id'] ?? null;
            if ($parentId) {
                $parent = $workspace->goals()->findOrFail($parentId);
                if ($parent->id === $goal?->id || $ranks[$parent->period] >= $ranks[$data['period']]) {
                    throw ValidationException::withMessages(['parent_id' => 'Escolha uma meta de um período maior deste ambiente.']);
                }
            }
            if ($goal && $goal->children()->get()->contains(fn ($child) => $ranks[$child->period] <= $ranks[$data['period']])) {
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
        validator(['status' => $status], ['status' => ['required', Rule::in(array_keys(Goal::STATUSES))]])->validate();
        $workspace->goals()->findOrFail($id)->update(['status' => $status]);
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
