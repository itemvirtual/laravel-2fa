<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\Fixtures\User;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class RecoveryCodesDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    public function test_confirmed_user_downloads_the_codes_as_txt()
    {
        $user = $this->createUser(true);

        $response = $this->actingAs($user, 'web')->get('/two-factor/recovery-codes/download');

        $response->assertOk()
            ->assertDownload('recovery-codes.txt')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertSame(implode(PHP_EOL, $user->getRecoveryCodes()) . PHP_EOL, $response->streamedContent());
    }

    public function test_unconfirmed_user_is_redirected_home()
    {
        $user = $this->createUser(true, false);

        $this->actingAs($user, 'web')
            ->get('/two-factor/recovery-codes/download')
            ->assertRedirect('/dashboard');
    }

    public function test_user_without_two_factor_is_redirected_home()
    {
        $this->actingAs($this->createUser(), 'web')
            ->get('/two-factor/recovery-codes/download')
            ->assertRedirect('/dashboard');
    }

    public function test_guest_cannot_download()
    {
        $this->get('/two-factor/recovery-codes/download')->assertRedirect('/login');
    }

    public function test_download_is_isolated_per_guard()
    {
        $customer = $this->createCustomer(true);

        $this->actingAs($customer, 'customer')->get('/customer/two-factor/recovery-codes/download')->assertOk();
        $this->get('/two-factor/recovery-codes/download')->assertRedirect('/login');
    }

    public function test_fields_show_the_link_when_editing_the_current_user()
    {
        $user = $this->createUser(true);
        $this->actingAs($user, 'web');

        $html = Laravel2fa::formFields('web', $user)->render();

        $this->assertStringContainsString('href="' . url('/two-factor/recovery-codes/download') . '"', $html);
        $this->assertStringContainsString(trans('laravel-2fa::laravel-2fa.download_recovery_codes'), $html);
    }

    public function test_fields_hide_the_link_when_editing_another_user()
    {
        $other = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret', 'two_factor_enabled' => true]);
        $this->confirmTwoFactor($other);
        $this->actingAs($this->createUser(true), 'web');

        $html = Laravel2fa::formFields('web', $other->fresh())->render();

        $this->assertStringNotContainsString('recovery-codes/download', $html);
    }

    public function test_fields_hide_the_link_when_the_setup_is_not_confirmed()
    {
        $user = $this->createUser(true, false);
        $this->actingAs($user, 'web');

        $html = Laravel2fa::formFields('web', $user)->render();

        $this->assertStringNotContainsString('recovery-codes/download', $html);
    }
}
