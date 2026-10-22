<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceAccess
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $workspace = $request->route('workspace');
        abort_unless($workspace->members()->whereKey($request->user()->id)->exists(), 404);
        if ($permission) {
            abort_unless($workspace->allows($request->user(), Permission::from($permission)), 403);
        }

        $request->session()->put('active_workspace_id', $workspace->id);

        return $next($request);
    }
}
