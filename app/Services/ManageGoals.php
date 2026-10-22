<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ManageGoals
{
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'period' => ['required', Rule::in(['annual', 'monthly'])],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'required_if:period,monthly', 'integer', 'between:1,12'],
            'target' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
        ];
    }

    protected function authorize(Workspace $workspace): void
    {
        abort_unless($workspace->members()->whereKey(auth()->id())->exists(), 404);
        Gate::authorize('goals.manage', $workspace);
    }

    public function create(Workspace $workspace, array $data): void
    {
        $this->authorize($workspace);
        $data = validator($data, self::rules())->validate();
        $workspace->goals()->create([
            'title' => $data['title'], 'period' => $data['period'], 'year' => $data['year'],
            'month' => $data['period'] === 'monthly' ? $data['month'] : null,
            'target_cents' => Money::cents($data['target']),
        ]);
    }

    public function progress(Workspace $workspace, int $id, string $current): void
    {
        $this->authorize($workspace);
        validator(['current' => $current], ['current' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99']])->validate();
        $workspace->goals()->findOrFail($id)->update(['current_cents' => Money::cents($current)]);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $this->authorize($workspace);
        $workspace->goals()->findOrFail($id)->delete();
    }
}
