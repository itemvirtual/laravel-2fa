<?php

namespace Itemvirtual\Laravel2fa\Traits;

trait TwoFactorAuthentication
{
    public function initializeTwoFactorAuthentication()
    {
        $this->mergeCasts([
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_last_timestamp' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
        ]);

        $this->hidden = array_values(array_unique(array_merge($this->hidden, [
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_last_timestamp',
        ])));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled === true;
    }

    public function hasTwoFactorConfirmed(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function getTwoFactorLabel(): string
    {
        return (string)($this->getAttribute('email') ?: $this->getAuthIdentifier());
    }

    public function getTwoFactorLastTimestamp(): ?int
    {
        return $this->two_factor_last_timestamp;
    }

    public function setTwoFactorLastTimestamp(int $timestamp): void
    {
        $this->forceFill(['two_factor_last_timestamp' => $timestamp])->save();
    }

    public function startTwoFactorSetup(string $secret): void
    {
        $this->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_last_timestamp' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function confirmTwoFactor(array $recoveryCodes): void
    {
        $this->forceFill([
            'two_factor_recovery_codes' => array_values($recoveryCodes),
            'two_factor_confirmed_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function getRecoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    public function replaceRecoveryCodes(array $recoveryCodes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();
    }

    public function useRecoveryCode(string $code): bool
    {
        $code = trim($code);
        $codes = $this->getRecoveryCodes();
        $match = null;

        foreach ($codes as $index => $stored) {
            if ($code !== '' && hash_equals((string)$stored, $code)) {
                $match = $index;
            }
        }

        if ($match === null) {
            return false;
        }

        unset($codes[$match]);
        $this->replaceRecoveryCodes($codes);

        return true;
    }

    public function resetTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_timestamp' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
