<?php

namespace Itemvirtual\Laravel2fa\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $guard = $guard ?: config('auth.defaults.guard');

        if (!Laravel2fa::requiresSetup($guard, Auth::guard($guard)->user())) {
            return $next($request);
        }

        if (Str::startsWith((string)$request->route()?->getName(), 'laravel-2fa.' . $guard . '.')) {
            return $next($request);
        }

        return redirect()->route(Laravel2fa::routeName($guard, 'setup'));
    }
}
