<?php

namespace Itemvirtual\Laravel2fa;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Mail\TwoFactorCodeMail;
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

        if ($user->getTwoFactorMethod() === 'email') {
            $this->sendEmailCode($request, $guard, $user);
        }

        return redirect()->route($this->routeName($guard, 'challenge'));
    }

    public function methodsFor(string $guard): array
    {
        $methods = array_values(array_intersect(['totp', 'email'], (array)$this->guardConfig($guard, 'methods', ['totp', 'email'])));

        return $methods ?: ['totp'];
    }

    public function availableMethods(string $guard, TwoFactorAuthenticatable $user): array
    {
        return array_values(array_filter($this->methodsFor($guard), function ($method) use ($user) {
            return $method !== 'email' || $user->getTwoFactorEmail() !== null;
        }));
    }

    public function updateSettings(Request $request, string $guard, TwoFactorAuthenticatable $user, bool $enabled, ?string $method = null): void
    {
        if (!$enabled) {
            $user->resetTwoFactor();
            $user->setTwoFactorEnabled(false);

            return;
        }

        if (!in_array($method, $this->availableMethods($guard, $user), true)) {
            throw new InvalidArgumentException('Method [' . $method . '] is not available for guard [' . $guard . '].');
        }

        $user->setTwoFactorEnabled(true);

        if ($user->hasTwoFactorConfirmed() && $user->getTwoFactorMethod() === $method) {
            return;
        }

        $user->startTwoFactorSetup($method === 'totp' ? $this->generateSecret() : null, $method);
        $user->confirmTwoFactor($this->generateRecoveryCodes());
    }

    public function formFields(string $guard, TwoFactorAuthenticatable $user): View
    {
        $current = $this->isCurrentUser($guard, $user);
        $totp = $current && $user->hasTwoFactorConfirmed() && $user->getTwoFactorMethod() === 'totp';

        return view($this->guardConfig($guard, 'views.fields', 'laravel-2fa::fields'), [
            'guard' => $guard,
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed' => $user->hasTwoFactorConfirmed(),
            'method' => $user->getTwoFactorMethod(),
            'methods' => $this->availableMethods($guard, $user),
            'recoveryCodes' => session()->get('laravel-2fa.recovery_codes', []),
            'downloadUrl' => $current ? route($this->routeName($guard, 'recovery-codes.download')) : null,
            'qrCode' => $totp ? $this->qrCodeSvg($user) : null,
            'secret' => $totp ? $user->getTwoFactorSecret() : null,
        ]);
    }

    private function isCurrentUser(string $guard, TwoFactorAuthenticatable $user): bool
    {
        $current = Auth::guard($guard)->user();

        return $current !== null && $current->getAuthIdentifier() === $user->getAuthIdentifier();
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticatable $user): array
    {
        if (!$user->hasTwoFactorConfirmed()) {
            return [];
        }

        $codes = $this->generateRecoveryCodes();
        $user->replaceRecoveryCodes($codes);
        $request->session()->flash('laravel-2fa.recovery_codes', $codes);

        return $codes;
    }

    public function emailSessionKey(string $guard): string
    {
        return 'laravel-2fa.' . $guard . '.email';
    }

    public function sendEmailCode(Request $request, string $guard, TwoFactorAuthenticatable $user): void
    {
        $length = max(4, (int)config('laravel-2fa.email.length', 6));
        $expires = max(1, (int)config('laravel-2fa.email.expires', 10));
        $code = str_pad((string)random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $request->session()->put($this->emailSessionKey($guard), [
            'hash' => $this->hashEmailCode($code),
            'expires_at' => now()->addMinutes($expires)->getTimestamp(),
            'attempts' => 0,
        ]);

        Mail::to($user->getTwoFactorEmail())->send(new TwoFactorCodeMail($code, $expires));
    }

    public function verifyEmailCode(Request $request, string $guard, string $code): bool
    {
        $key = $this->emailSessionKey($guard);
        $stored = $request->session()->get($key);
        $code = preg_replace('/\s+/', '', $code);

        if (!is_array($stored) || $code === '' || $stored['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget($key);

            return false;
        }

        if (!hash_equals($stored['hash'], $this->hashEmailCode($code))) {
            $stored['attempts']++;

            if ($stored['attempts'] >= (int)config('laravel-2fa.email.max_attempts', 5)) {
                $request->session()->forget($key);
            } else {
                $request->session()->put($key, $stored);
            }

            return false;
        }

        $request->session()->forget($key);

        return true;
    }

    public function verifyCode(Request $request, string $guard, TwoFactorAuthenticatable $user, string $code): bool
    {
        if ($user->getTwoFactorMethod() === 'email') {
            return $this->verifyEmailCode($request, $guard, $code);
        }

        return $this->verify($user, $code);
    }

    public function maskEmail(?string $email): string
    {
        if (!$email || strpos($email, '@') === false) {
            return '';
        }

        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1) . str_repeat('*', max(mb_strlen($name) - 1, 1)) . '@' . $domain;
    }

    private function hashEmailCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string)config('app.key'));
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
        $request->session()->forget([$this->sessionKey($guard), $this->emailSessionKey($guard)]);
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
