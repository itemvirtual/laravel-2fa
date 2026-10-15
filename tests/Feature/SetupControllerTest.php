<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Itemvirtual\Laravel2fa\Tests\TestCase;

class SetupControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    public function test_guest_is_redirected()
    {
        $this->get('/two-factor/setup')->assertRedirect();
    }

    public function test_user_without_flag_is_sent_home()
    {
        $user = $this->createUser();

        $this->actingAs($user, 'web')->get('/two-factor/setup')->assertRedirect('/dashboard');
        $this->post('/two-factor/setup', ['code' => '000000'])->assertRedirect('/dashboard');
        $this->post('/two-factor/recovery-codes', ['code' => '000000'])->assertRedirect('/dashboard');
        $this->delete('/two-factor/setup', ['code' => '000000'])->assertRedirect('/dashboard');

        $this->assertNull($user->fresh()->getTwoFactorSecret());
    }

    public function test_show_generates_a_secret_once()
    {
        $user = $this->createUser(true, false);

        $this->actingAs($user, 'web')
            ->get('/two-factor/setup')
            ->assertOk()
            ->assertSee('setup:web:pending')
            ->assertSee('qr:yes');

        $secret = $user->fresh()->getTwoFactorSecret();
        $this->assertNotNull($secret);
        $this->assertFalse($user->fresh()->hasTwoFactorConfirmed());

        $this->get('/two-factor/setup')->assertSee('secret:' . $secret);
        $this->assertSame($secret, $user->fresh()->getTwoFactorSecret());
    }

    public function test_confirm_with_valid_code_shows_codes_once()
    {
        $user = $this->createUser(true, false);
        $this->actingAs($user, 'web')->get('/two-factor/setup');

        $this->post('/two-factor/setup', ['code' => $this->currentCode($user)])
            ->assertRedirect('/two-factor/setup')
            ->assertSessionHas('laravel-2fa.status', 'two-factor-confirmed');

        $user = $user->fresh();
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertCount(8, $user->getRecoveryCodes());

        $this->get('/two-factor/setup')
            ->assertSee('setup:web:confirmed')
            ->assertSee('qr:no')
            ->assertSee('codes:' . implode(',', $user->getRecoveryCodes()));

        $this->get('/two-factor/setup')->assertSee('codes:' . PHP_EOL, false);
    }

    public function test_confirm_with_invalid_code_fails()
    {
        $user = $this->createUser(true, false);
        $this->actingAs($user, 'web')->get('/two-factor/setup');

        $this->post('/two-factor/setup', ['code' => '000000'])
            ->assertRedirect('/two-factor/setup')
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorConfirmed());
    }

    public function test_regenerate_recovery_codes_requires_a_valid_code()
    {
        $user = $this->createUser(true);
        $old = $user->getRecoveryCodes();

        $this->actingAs($user, 'web')
            ->post('/two-factor/recovery-codes', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame($old, $user->fresh()->getRecoveryCodes());

        $this->post('/two-factor/recovery-codes', ['code' => $this->currentCode($user)])
            ->assertRedirect('/two-factor/setup')
            ->assertSessionHas('laravel-2fa.status', 'recovery-codes-generated')
            ->assertSessionHas('laravel-2fa.recovery_codes');

        $this->assertNotSame($old, $user->fresh()->getRecoveryCodes());
    }

    public function test_reset_requires_a_valid_code_and_keeps_the_flag()
    {
        $user = $this->createUser(true);

        $this->actingAs($user, 'web')
            ->delete('/two-factor/setup', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertTrue($user->fresh()->hasTwoFactorConfirmed());

        $this->delete('/two-factor/setup', ['code' => $this->currentCode($user)])
            ->assertRedirect('/two-factor/setup')
            ->assertSessionHas('laravel-2fa.status', 'two-factor-reset');

        $user = $user->fresh();
        $this->assertFalse($user->hasTwoFactorConfirmed());
        $this->assertTrue($user->hasTwoFactorEnabled());

        $this->get('/dashboard')->assertRedirect('/two-factor/setup');
    }

    public function test_setup_is_scoped_to_its_guard()
    {
        $customer = $this->createCustomer(true, false);

        $this->actingAs($customer, 'customer')->get('/two-factor/setup')->assertRedirect();
        $this->get('/customer/two-factor/setup')->assertOk()->assertSee('setup:customer:pending');
    }
}
