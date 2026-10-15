<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Itemvirtual\Laravel2fa\Http\Middleware\EnsureTwoFactorEnabled;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class EnforcedMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    public function test_alias_is_registered()
    {
        $this->assertSame(EnsureTwoFactorEnabled::class, app('router')->getMiddleware()['laravel-2fa.enforced']);
    }

    public function test_user_without_flag_passes_through()
    {
        $this->actingAs($this->createUser(), 'web')->get('/dashboard')->assertOk();
    }

    public function test_user_with_flag_and_no_configuration_is_redirected_to_setup()
    {
        $this->actingAs($this->createUser(true, false), 'web')->get('/dashboard')->assertRedirect('/two-factor/setup');
    }

    public function test_package_routes_are_allowed()
    {
        $this->actingAs($this->createUser(true, false), 'web')->get('/two-factor/setup')->assertOk();
    }

    public function test_user_with_flag_and_configuration_passes_through()
    {
        $this->actingAs($this->createUser(true), 'web')->get('/dashboard')->assertOk();
    }

    public function test_guard_not_configured_passes_through()
    {
        $this->removeGuard('web');

        $this->actingAs($this->createUser(true, false), 'web')->get('/dashboard')->assertOk();
    }

    public function test_is_per_guard()
    {
        $this->createUser(true, false);
        $customer = $this->createCustomer(true, false);

        $this->actingAs($customer, 'customer')->get('/customer/dashboard')->assertRedirect('/customer/two-factor/setup');
    }
}
