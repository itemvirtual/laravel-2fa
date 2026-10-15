<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Itemvirtual\Laravel2fa\Tests\Fixtures\Customer;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class TwoFactorAuthenticationTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    public function test_starts_disabled_and_unconfirmed()
    {
        $user = $this->createUser();

        $this->assertFalse($user->two_factor_enabled);
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertFalse($user->hasTwoFactorConfirmed());
    }

    public function test_enabled_flag_is_independent_from_the_configuration()
    {
        $user = $this->createUser(true, false);

        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertFalse($user->hasTwoFactorConfirmed());

        $this->confirmTwoFactor($user);
        $user->forceFill(['two_factor_enabled' => false])->save();
        $user = $user->fresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertNotNull($user->getTwoFactorSecret());
    }

    public function test_setup_and_confirm()
    {
        $user = $this->createUser();

        $user->startTwoFactorSetup('SECRET123');
        $this->assertFalse($user->fresh()->hasTwoFactorConfirmed());
        $this->assertSame('SECRET123', $user->fresh()->getTwoFactorSecret());

        $user->confirmTwoFactor(['code-1', 'code-2']);
        $user = $user->fresh();

        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertSame(['code-1', 'code-2'], $user->getRecoveryCodes());
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_secret_and_recovery_codes_are_encrypted()
    {
        $user = $this->createUser();
        $user->startTwoFactorSetup('SECRET123');
        $user->confirmTwoFactor(['code-1']);

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotSame('SECRET123', $row->two_factor_secret);
        $this->assertStringNotContainsString('code-1', $row->two_factor_recovery_codes);
        $this->assertSame('SECRET123', decrypt($row->two_factor_secret, false));
    }

    public function test_sensitive_attributes_are_hidden()
    {
        $user = $this->createUser();
        $user->startTwoFactorSetup('SECRET123');
        $user->confirmTwoFactor(['code-1']);
        $user->setTwoFactorLastTimestamp(123);

        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
        $this->assertArrayNotHasKey('two_factor_last_timestamp', $array);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayHasKey('two_factor_confirmed_at', $array);
    }

    public function test_last_timestamp_is_stored_as_integer()
    {
        $user = $this->createUser();

        $this->assertNull($user->getTwoFactorLastTimestamp());

        $user->setTwoFactorLastTimestamp(57000000);

        $this->assertSame(57000000, $user->fresh()->getTwoFactorLastTimestamp());
    }

    public function test_label_uses_email()
    {
        $this->assertSame('jane@example.com', $this->createUser()->getTwoFactorLabel());
    }

    public function test_recovery_code_works_once()
    {
        $user = $this->createUser();
        $user->startTwoFactorSetup('SECRET123');
        $user->confirmTwoFactor(['code-1', 'code-2']);

        $this->assertTrue($user->useRecoveryCode(' code-1 '));
        $this->assertFalse($user->fresh()->useRecoveryCode('code-1'));
        $this->assertSame(['code-2'], $user->fresh()->getRecoveryCodes());
        $this->assertFalse($user->fresh()->useRecoveryCode(''));
        $this->assertFalse($user->fresh()->useRecoveryCode('wrong'));
    }

    public function test_replace_recovery_codes()
    {
        $user = $this->createUser();
        $user->startTwoFactorSetup('SECRET123');
        $user->confirmTwoFactor(['code-1']);

        $user->replaceRecoveryCodes(['new-1', 'new-2']);

        $this->assertSame(['new-1', 'new-2'], $user->fresh()->getRecoveryCodes());
    }

    public function test_new_setup_resets_previous_state()
    {
        $user = $this->createUser();
        $user->startTwoFactorSetup('SECRET123');
        $user->confirmTwoFactor(['code-1']);
        $user->setTwoFactorLastTimestamp(123);

        $user->startTwoFactorSetup('OTHER456');
        $user = $user->fresh();

        $this->assertFalse($user->hasTwoFactorConfirmed());
        $this->assertSame([], $user->getRecoveryCodes());
        $this->assertNull($user->getTwoFactorLastTimestamp());
    }

    public function test_reset_clears_the_configuration_but_keeps_the_flag()
    {
        $user = $this->createUser(true);

        $user->resetTwoFactor();
        $user = $user->fresh();

        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertFalse($user->hasTwoFactorConfirmed());
        $this->assertNull($user->getTwoFactorSecret());
        $this->assertSame([], $user->getRecoveryCodes());
    }

    public function test_guards_models_are_independent()
    {
        $user = $this->createUser();
        $customer = Customer::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

        $customer->startTwoFactorSetup('CUSTOMER123');
        $customer->confirmTwoFactor(['customer-code']);

        $this->assertTrue($customer->fresh()->hasTwoFactorConfirmed());
        $this->assertFalse($user->fresh()->hasTwoFactorConfirmed());
    }
}
