<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\Fixtures\User;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class FormFieldsQrCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();

        config()->set('laravel-2fa.guards.web.methods', ['totp', 'email']);
    }

    private function enable(User $user, string $method): User
    {
        Laravel2fa::updateSettings(Request::create('/profile', 'POST'), 'web', $user, true, $method);

        return $user->fresh();
    }

    public function test_current_totp_user_sees_the_qr_code_and_the_secret()
    {
        $user = $this->enable($this->createUser(), 'totp');
        $this->actingAs($user, 'web');

        $html = Laravel2fa::formFields('web', $user)->render();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('<?xml', $html);
        $this->assertStringContainsString($user->getTwoFactorSecret(), $html);
        $this->assertStringContainsString(trans('laravel-2fa::laravel-2fa.fields_totp_text'), $html);
    }

    public function test_qr_code_is_hidden_when_editing_another_user()
    {
        $other = $this->enable(User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret']), 'totp');
        $this->actingAs($this->createUser(), 'web');

        $html = Laravel2fa::formFields('web', $other)->render();

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString($other->getTwoFactorSecret(), $html);
    }

    public function test_qr_code_is_hidden_for_the_email_method()
    {
        $user = $this->enable($this->createUser(), 'email');
        $this->actingAs($user, 'web');

        $html = Laravel2fa::formFields('web', $user)->render();

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString(trans('laravel-2fa::laravel-2fa.fields_totp_text'), $html);
    }

    public function test_qr_code_is_hidden_when_two_factor_is_disabled()
    {
        $user = $this->createUser();
        $this->actingAs($user, 'web');

        $html = Laravel2fa::formFields('web', $user)->render();

        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_qr_code_matches_the_stored_secret()
    {
        $user = $this->enable($this->createUser(), 'totp');

        $this->assertStringContainsString('secret=' . $user->getTwoFactorSecret(), Laravel2fa::otpauthUrl($user));
        $this->assertTrue(Laravel2fa::verify($user, $this->currentCode($user)));
    }
}
