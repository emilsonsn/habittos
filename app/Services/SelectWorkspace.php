<?php

namespace App\Services;

use App\Enums\FinanceSection;
use App\Enums\GoalSection;
use App\Enums\NoteSection;
use App\Enums\WorkspaceModule;
use App\Models\User;
use App\Models\Workspace;

class SelectWorkspace
{
    public function current(User $user, ?int $routeWorkspaceId = null): ?Workspace
    {
        $workspaces = $user->workspaces()->orderBy('name')->get();
        $workspace = $workspaces->firstWhere('id', $routeWorkspaceId)
            ?? $workspaces->firstWhere('id', session('active_workspace_id'))
            ?? $workspaces->first();
        session(['active_workspace_id' => $workspace?->id]);

        return $workspace;
    }

    public function handle(User $user, int $workspaceId, string $module = WorkspaceModule::Finance->value, ?string $month = null): string
    {
        $workspace = $user->workspaces()->findOrFail($workspaceId);
        session(['active_workspace_id' => $workspace->id]);

        if ($module === WorkspaceModule::Home->value) {
            return route(WorkspaceModule::Home->value);
        }

        if ($module === WorkspaceModule::Settings->value) {
            return route('settings.index', $workspace);
        }

        $financeSection = collect(FinanceSection::cases())->first(fn ($section) => $section->routeName() === $module);
        if ($financeSection) {
            $module = WorkspaceModule::Finance->value;
        }

        $goalSection = collect(GoalSection::cases())->first(fn ($section) => $section->routeName() === $module);
        if ($goalSection) {
            $module = WorkspaceModule::Goals->value;
        }

        $noteSection = collect(NoteSection::cases())->first(fn ($section) => $section->routeName() === $module);
        if ($noteSection) {
            $module = WorkspaceModule::Notes->value;
        }

        if ($module === 'routine.index') {
            $module = WorkspaceModule::Routine->value;
        }

        $preferred = WorkspaceModule::tryFrom($module);
        $modules = collect([$preferred, ...WorkspaceModule::cases()])->filter()->unique(fn ($item) => $item->value);
        foreach ($modules as $candidate) {
            $permission = $candidate->permission();
            if (! $permission || ! $workspace->allows($user, $permission)) {
                continue;
            }
            if ($candidate === WorkspaceModule::Finance) {
                $month ??= app(FinancialCalendar::class)->monthForDate($workspace, now()->toDateString());

                return route(($financeSection ?? FinanceSection::Dashboard)->routeName(), [$workspace, 'month' => $month]);
            }

            if ($candidate === WorkspaceModule::Routine) {
                return route('routine.index', $workspace);
            }

            if ($candidate === WorkspaceModule::Notes) {
                return route(($noteSection ?? NoteSection::Dashboard)->routeName(), $workspace);
            }

            return route($candidate === WorkspaceModule::Goals ? ($goalSection ?? GoalSection::Dashboard)->routeName() : 'members.index', $workspace);
        }

        return route(WorkspaceModule::Home->value);
    }
}
