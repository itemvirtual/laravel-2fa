<?php

namespace Itemvirtual\Laravel2fa\Contracts;

interface TwoFactorAuthenticatable
{
    public function hasTwoFactorEnabled(): bool;

    public function setTwoFactorEnabled(bool $enabled): void;

    public function hasTwoFactorConfirmed(): bool;

    public function getTwoFactorMethod(): ?string;

    public function getTwoFactorSecret(): ?string;

    public function getTwoFactorLabel(): string;

    public function getTwoFactorEmail(): ?string;

    public function getTwoFactorLastTimestamp(): ?int;

    public function setTwoFactorLastTimestamp(int $timestamp): void;

    public function startTwoFactorSetup(?string $secret, string $method = 'totp'): void;

    public function confirmTwoFactor(array $recoveryCodes): void;

    public function getRecoveryCodes(): array;

    public function replaceRecoveryCodes(array $recoveryCodes): void;

    public function useRecoveryCode(string $code): bool;

    public function resetTwoFactor(): void;
}
