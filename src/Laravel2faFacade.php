<?php

namespace Itemvirtual\Laravel2fa;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool hasGuard(string $guard)
 * @method static bool requiresChallenge(string $guard, mixed $user)
 * @method static bool requiresSetup(string $guard, mixed $user)
 * @method static string routeName(string $guard, string $name)
 * @method static string sessionKey(string $guard)
 * @method static \Illuminate\Http\RedirectResponse|null challengeIfRequired(\Illuminate\Http\Request $request, string $guard)
 * @method static bool hasPendingLogin(\Illuminate\Http\Request $request, string $guard)
 * @method static \Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable|null pendingUser(\Illuminate\Http\Request $request, string $guard)
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null completeLogin(\Illuminate\Http\Request $request, string $guard)
 * @method static void clearPendingLogin(\Illuminate\Http\Request $request, string $guard)
 * @method static bool verify(\Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable $user, string $code)
 * @method static string generateSecret()
 * @method static array generateRecoveryCodes()
 * @method static string otpauthUrl(\Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable $user)
 * @method static string qrCodeSvg(\Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable $user, int $size = 192)
 * @method static \PragmaRX\Google2FA\Google2FA google2fa()
 * @method static array guards()
 * @method static array tables()
 * @method static mixed guardConfig(string $guard, string|null $key = null, mixed $default = null)
 * @method static array providerFor(string $guard)
 * @method static string|null modelFor(string $guard)
 * @method static string tableFor(string $guard)
 *
 * @see \Itemvirtual\Laravel2fa\Laravel2fa
 */
class Laravel2faFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-2fa';
    }
}
