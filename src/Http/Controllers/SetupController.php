<?php

namespace Itemvirtual\Laravel2fa\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use LogicException;

class SetupController extends Controller
{
    public function show(Request $request)
    {
        $guard = $request->route('guard');
        $user = $this->user($request, $guard);

        if (!$user->hasTwoFactorEnabled()) {
            return $this->home($guard);
        }

        $confirmed = $user->hasTwoFactorConfirmed();

        if (!$confirmed && $user->getTwoFactorSecret() === null) {
            $user->startTwoFactorSetup(Laravel2fa::generateSecret());
        }

        return view(Laravel2fa::guardConfig($guard, 'views.setup'), [
            'guard' => $guard,
            'user' => $user,
            'confirmed' => $confirmed,
            'secret' => $confirmed ? null : $user->getTwoFactorSecret(),
            'qrCode' => $confirmed ? null : Laravel2fa::qrCodeSvg($user),
            'recoveryCodes' => $request->session()->get('laravel-2fa.recovery_codes', []),
            'redirect' => Laravel2fa::guardConfig($guard, 'redirect', '/'),
            'routes' => [
                'confirm' => route(Laravel2fa::routeName($guard, 'setup.store')),
                'reset' => route(Laravel2fa::routeName($guard, 'setup.destroy')),
                'recoveryCodes' => route(Laravel2fa::routeName($guard, 'recovery-codes')),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $guard = $request->route('guard');
        $user = $this->user($request, $guard);
        $setup = redirect()->route(Laravel2fa::routeName($guard, 'setup'));

        if (!$user->hasTwoFactorEnabled()) {
            return $this->home($guard);
        }

        if ($user->hasTwoFactorConfirmed()) {
            return $setup;
        }

        $request->validate(['code' => ['required', 'string']]);

        if (!Laravel2fa::verify($user, $request->input('code'))) {
            return $setup->withErrors(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $codes = Laravel2fa::generateRecoveryCodes();
        $user->confirmTwoFactor($codes);

        return $setup
            ->with('laravel-2fa.status', 'two-factor-confirmed')
            ->with('laravel-2fa.recovery_codes', $codes);
    }

    public function recoveryCodes(Request $request)
    {
        $guard = $request->route('guard');
        $user = $this->user($request, $guard);
        $setup = redirect()->route(Laravel2fa::routeName($guard, 'setup'));

        if (!$user->hasTwoFactorEnabled()) {
            return $this->home($guard);
        }

        if (!$user->hasTwoFactorConfirmed()) {
            return $setup;
        }

        $request->validate(['code' => ['required', 'string']]);

        if (!Laravel2fa::verify($user, $request->input('code'))) {
            return $setup->withErrors(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $codes = Laravel2fa::generateRecoveryCodes();
        $user->replaceRecoveryCodes($codes);

        return $setup
            ->with('laravel-2fa.status', 'recovery-codes-generated')
            ->with('laravel-2fa.recovery_codes', $codes);
    }

    public function destroy(Request $request)
    {
        $guard = $request->route('guard');
        $user = $this->user($request, $guard);
        $setup = redirect()->route(Laravel2fa::routeName($guard, 'setup'));

        if (!$user->hasTwoFactorEnabled()) {
            return $this->home($guard);
        }

        if (!$user->hasTwoFactorConfirmed()) {
            return $setup;
        }

        $request->validate(['code' => ['required', 'string']]);

        if (!Laravel2fa::verify($user, $request->input('code'))) {
            return $setup->withErrors(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $user->resetTwoFactor();

        return $setup->with('laravel-2fa.status', 'two-factor-reset');
    }

    private function user(Request $request, string $guard): TwoFactorAuthenticatable
    {
        $user = $request->user($guard);

        if (!$user instanceof TwoFactorAuthenticatable) {
            throw new LogicException('The [' . $guard . '] user model must implement ' . TwoFactorAuthenticatable::class . '.');
        }

        return $user;
    }

    private function home(string $guard)
    {
        return redirect(Laravel2fa::guardConfig($guard, 'redirect', '/'));
    }
}
