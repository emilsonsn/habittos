<?php

namespace App\Services;

use App\Enums\FinanceSection;
use App\Enums\Permission;
use App\Models\User;

class SelectWorkspace
{
    public function handle(User $user, int $workspaceId, string $module = 'finance', ?string $month = null): string
    {
        $workspace = $user->workspaces()->findOrFail($workspaceId);
        session(['active_workspace_id' => $workspace->id]);

        if ($module === 'settings') {
            return route('settings.index', $workspace);
        }

        $financeSection = collect(FinanceSection::cases())->first(fn ($section) => $section->routeName() === $module);
        if ($financeSection) {
            $module = 'finance';
        }

        $modules = ['finance' => Permission::FinanceView, 'goals' => Permission::GoalsView, 'members' => Permission::MembersManage];
        foreach (array_unique([$module, ...array_keys($modules)]) as $candidate) {
            if (! isset($modules[$candidate]) || ! $workspace->allows($user, $modules[$candidate])) {
                continue;
            }
            if ($candidate === 'finance') {
                $month ??= app(FinancialCalendar::class)->monthForDate($workspace, now()->toDateString());

                return route(($financeSection ?? FinanceSection::Dashboard)->routeName(), [$workspace, 'month' => $month]);
            }

            return route($candidate === 'goals' ? 'goals.index' : 'members.index', $workspace);
        }

        return route('workspaces.index');
    }
}
