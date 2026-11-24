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
        $user = Laravel2fa::pendingUser($request, $guard);

        if ($user === null) {
            return $this->restart($request, $guard);
        }

        return view(Laravel2fa::guardConfig($guard, 'views.challenge'), [
            'guard' => $guard,
            'action' => route(Laravel2fa::routeName($guard, 'challenge.store')),
            'method' => $user->getTwoFactorMethod(),
            'email' => Laravel2fa::maskEmail($user->getTwoFactorEmail()),
            'resendAction' => route(Laravel2fa::routeName($guard, 'challenge.resend')),
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
            return $this->restart($request, $guard);
        }

        $challenge = redirect()->route(Laravel2fa::routeName($guard, 'challenge'));

        if ($request->filled('code')) {
            if (!Laravel2fa::verifyCode($request, $guard, $user, $request->input('code'))) {
                return $challenge->withErrors(['code' => trans('laravel-2fa::laravel-2fa.invalid_code')]);
            }
        } elseif ($request->filled('recovery_code')) {
            if (!$user->useRecoveryCode($request->input('recovery_code'))) {
                return $challenge->withErrors(['recovery_code' => trans('laravel-2fa::laravel-2fa.invalid_recovery_code')]);
            }
        } else {
            return $challenge->withErrors(['code' => trans('laravel-2fa::laravel-2fa.missing_code')]);
        }

        Laravel2fa::completeLogin($request, $guard);

        return redirect()->intended(Laravel2fa::guardConfig($guard, 'redirect', '/'));
    }

    public function resend(Request $request)
    {
        $guard = $request->route('guard');
        $user = Laravel2fa::pendingUser($request, $guard);

        if ($user === null) {
            return $this->restart($request, $guard);
        }

        $challenge = redirect()->route(Laravel2fa::routeName($guard, 'challenge'));

        if ($user->getTwoFactorMethod() !== 'email') {
            return $challenge;
        }

        Laravel2fa::sendEmailCode($request, $guard, $user);

        return $challenge->with('laravel-2fa.status', 'code-sent');
    }

    private function restart(Request $request, string $guard)
    {
        Laravel2fa::clearPendingLogin($request, $guard);

        return redirect($this->loginUrl($guard));
    }

    private function loginUrl(string $guard): string
    {
        $route = Laravel2fa::guardConfig($guard, 'login_route');

        return $route && Route::has($route) ? route($route) : url('/');
    }
}
