<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirstWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $hasWorkspace = $request->user()->workspaces()->exists();
        if ($request->routeIs('onboarding')) {
            return $hasWorkspace ? new RedirectResponse(route('home')) : $next($request);
        }

        return $hasWorkspace ? $next($request) : new RedirectResponse(route('onboarding'));
    }
}
