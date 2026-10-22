<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('layout', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $workspaces = $user->workspaces()->orderBy('name')->get();
            $routeWorkspace = request()->route('workspace');
            $activeWorkspace = $workspaces->firstWhere('id', $routeWorkspace?->id)
                ?? $workspaces->firstWhere('id', session('active_workspace_id'))
                ?? $workspaces->first();

            // Revalida o acesso, inclusive quando a participação foi removida.
            session(['active_workspace_id' => $activeWorkspace?->id]);
            $view->with('availableWorkspaces', $workspaces)
                ->with('activeWorkspace', $activeWorkspace);
        });

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user, Workspace $workspace) => $workspace->allows($user, $permission));
        }
    }
}
