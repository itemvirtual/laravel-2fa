<?php

namespace Itemvirtual\Laravel2fa;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use PragmaRX\Google2FA\Google2FA;

class Laravel2fa
{
    private ?Google2FA $google2fa = null;

    public function hasGuard(string $guard): bool
    {
        return in_array($guard, $this->guards(), true);
    }

    public function requiresChallenge(string $guard, $user): bool
    {
        return $this->hasGuard($guard)
            && $user instanceof TwoFactorAuthenticatable
            && $user->hasTwoFactorEnabled()
            && $user->hasTwoFactorConfirmed();
    }

    public function requiresSetup(string $guard, $user): bool
    {
        return $this->hasGuard($guard)
            && $user instanceof TwoFactorAuthenticatable
            && $user->hasTwoFactorEnabled()
            && !$user->hasTwoFactorConfirmed();
    }

    public function routeName(string $guard, string $name): string
    {
        return 'laravel-2fa.' . $guard . '.' . $name;
    }

    public function sessionKey(string $guard): string
    {
        return 'laravel-2fa.' . $guard . '.pending';
    }

    public function challengeIfRequired(Request $request, string $guard): ?RedirectResponse
    {
        $user = Auth::guard($guard)->user();

        if (!$this->requiresChallenge($guard, $user)) {
            return null;
        }

        Auth::guard($guard)->logout();

        $request->session()->put($this->sessionKey($guard), [
            'id' => $user->getAuthIdentifier(),
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->route($this->routeName($guard, 'challenge'));
    }

    public function hasPendingLogin(Request $request, string $guard): bool
    {
        return $request->session()->has($this->sessionKey($guard));
    }

    public function pendingUser(Request $request, string $guard): ?TwoFactorAuthenticatable
    {
        $pending = $request->session()->get($this->sessionKey($guard));

        if (!is_array($pending) || !isset($pending['id'])) {
            return null;
        }

        $user = Auth::guard($guard)->getProvider()->retrieveById($pending['id']);

        if (!$this->requiresChallenge($guard, $user)) {
            return null;
        }

        return $user;
    }

    public function completeLogin(Request $request, string $guard): ?Authenticatable
    {
        $user = $this->pendingUser($request, $guard);

        if ($user === null) {
            return null;
        }

        $remember = (bool)$request->session()->get($this->sessionKey($guard) . '.remember', false);

        $this->clearPendingLogin($request, $guard);
        Auth::guard($guard)->login($user, $remember);
        $request->session()->regenerate();

        return $user;
    }

    public function clearPendingLogin(Request $request, string $guard): void
    {
        $request->session()->forget($this->sessionKey($guard));
    }

    public function verify(TwoFactorAuthenticatable $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        $secret = $user->getTwoFactorSecret();

        if ($secret === null || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = $this->google2fa()->verifyKeyNewer(
            $secret,
            $code,
            $user->getTwoFactorLastTimestamp() ?? 0,
            (int)config('laravel-2fa.window', 1)
        );

        if ($timestamp === false) {
            return false;
        }

        $user->setTwoFactorLastTimestamp((int)$timestamp);

        return true;
    }

    public function generateSecret(): string
    {
        return $this->google2fa()->generateSecretKey(32);
    }

    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < (int)config('laravel-2fa.recovery_codes', 8); $i++) {
            $codes[] = Str::random(10) . '-' . Str::random(10);
        }

        return $codes;
    }

    public function otpauthUrl(TwoFactorAuthenticatable $user): string
    {
        return $this->google2fa()->getQRCodeUrl(
            (string)config('laravel-2fa.issuer'),
            $user->getTwoFactorLabel(),
            (string)$user->getTwoFactorSecret()
        );
    }

    public function qrCodeSvg(TwoFactorAuthenticatable $user, int $size = 192): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        return trim(preg_replace('/^<\?xml.*?\?>/', '', $writer->writeString($this->otpauthUrl($user))));
    }

    public function google2fa(): Google2FA
    {
        if ($this->google2fa === null) {
            $this->google2fa = new Google2FA();
        }

        return $this->google2fa;
    }

    public function guards(): array
    {
        return array_keys(config('laravel-2fa.guards', []));
    }

    public function tables(): array
    {
        $tables = [];

        foreach ($this->guards() as $guard) {
            $tables[] = $this->tableFor($guard);
        }

        return array_values(array_unique($tables));
    }

    public function guardConfig(string $guard, ?string $key = null, $default = null)
    {
        $config = config('laravel-2fa.guards.' . $guard);

        if (!is_array($config)) {
            throw new InvalidArgumentException('Guard [' . $guard . '] is not configured in laravel-2fa.');
        }

        return $key === null ? $config : Arr::get($config, $key, $default);
    }

    public function providerFor(string $guard): array
    {
        $provider = config('auth.guards.' . $guard . '.provider');

        if (!$provider) {
            throw new InvalidArgumentException('Guard [' . $guard . '] is not defined in config/auth.php.');
        }

        $config = config('auth.providers.' . $provider);

        if (!is_array($config)) {
            throw new InvalidArgumentException('Auth provider [' . $provider . '] is not defined in config/auth.php.');
        }

        return $config;
    }

    public function modelFor(string $guard): ?string
    {
        return $this->providerFor($guard)['model'] ?? null;
    }

    public function tableFor(string $guard): string
    {
        $provider = $this->providerFor($guard);

        if (($provider['driver'] ?? null) === 'database' && !empty($provider['table'])) {
            return $provider['table'];
        }

        if (!empty($provider['model']) && class_exists($provider['model'])) {
            return (new $provider['model']())->getTable();
        }

        throw new InvalidArgumentException('Unable to resolve the table for guard [' . $guard . '].');
    }
}
