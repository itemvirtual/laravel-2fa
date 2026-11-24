<?php

namespace Itemvirtual\Laravel2fa\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use LogicException;

class RecoveryCodesController extends Controller
{
    public function download(Request $request)
    {
        $guard = $request->route('guard');
        $user = $request->user($guard);

        if (!$user instanceof TwoFactorAuthenticatable) {
            throw new LogicException('The [' . $guard . '] user model must implement ' . TwoFactorAuthenticatable::class . '.');
        }

        if (!$user->hasTwoFactorEnabled() || !$user->hasTwoFactorConfirmed()) {
            return redirect(Laravel2fa::guardConfig($guard, 'redirect', '/'));
        }

        return response()->streamDownload(function () use ($user) {
            echo implode(PHP_EOL, $user->getRecoveryCodes()) . PHP_EOL;
        }, 'recovery-codes.txt', ['Content-Type' => 'text/plain']);
    }
}
