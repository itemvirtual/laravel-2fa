<?php

namespace Itemvirtual\Laravel2fa\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

class ChallengeController extends Controller
{
    public function show(Request $request)
    {
        $guard = $request->route('guard');

        if (Laravel2fa::pendingUser($request, $guard) === null) {
            Laravel2fa::clearPendingLogin($request, $guard);

            return redirect($this->loginUrl($guard));
        }

        return view(Laravel2fa::guardConfig($guard, 'views.challenge'), [
            'guard' => $guard,
            'action' => route(Laravel2fa::routeName($guard, 'challenge.store')),
        ]);
    }

    public function store(Request $request)
    {
        $guard = $request->route('guard');

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = Laravel2fa::pendingUser($request, $guard);

        if ($user === null) {
            Laravel2fa::clearPendingLogin($request, $guard);

            return redirect($this->loginUrl($guard));
        }

        $challenge = redirect()->route(Laravel2fa::routeName($guard, 'challenge'));

        if ($request->filled('code')) {
            if (!Laravel2fa::verify($user, $request->input('code'))) {
                return $challenge->withErrors(['code' => __('The provided two factor authentication code was invalid.')]);
            }
        } elseif ($request->filled('recovery_code')) {
            if (!$user->useRecoveryCode($request->input('recovery_code'))) {
                return $challenge->withErrors(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
            }
        } else {
            return $challenge->withErrors(['code' => __('Please provide a two factor authentication code.')]);
        }

        Laravel2fa::completeLogin($request, $guard);

        return redirect()->intended(Laravel2fa::guardConfig($guard, 'redirect', '/'));
    }

    private function loginUrl(string $guard): string
    {
        $route = Laravel2fa::guardConfig($guard, 'login_route');

        return $route && Route::has($route) ? route($route) : url('/');
    }
}
