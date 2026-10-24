<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SelectWorkspace;
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

            $routeWorkspace = request()->route('workspace');
            $view->with('activeWorkspace', app(SelectWorkspace::class)->current($user, $routeWorkspace?->id));
        });

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user, Workspace $workspace) => $workspace->allows($user, $permission));
        }
    }
}
