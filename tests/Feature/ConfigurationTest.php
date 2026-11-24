<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use InvalidArgumentException;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\Fixtures\Customer;
use Itemvirtual\Laravel2fa\Tests\Fixtures\User;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_global_options_have_defaults()
    {
        $this->assertNotEmpty(config('laravel-2fa.issuer'));
        $this->assertSame(1, config('laravel-2fa.window'));
        $this->assertSame(8, config('laravel-2fa.recovery_codes'));
    }

    public function test_package_only_ships_the_web_guard()
    {
        $config = require __DIR__ . '/../../config/config.php';

        $this->assertSame(['web'], array_keys($config['guards']));
    }

    public function test_project_guards_are_configured()
    {
        $this->assertSame(['web', 'customer'], Laravel2fa::guards());

        foreach (Laravel2fa::guards() as $guard) {
            $config = Laravel2fa::guardConfig($guard);

            foreach (['prefix', 'middleware', 'login_route', 'redirect'] as $key) {
                $this->assertArrayHasKey($key, $config);
            }

            $this->assertNotEmpty(Laravel2fa::guardConfig($guard, 'views.challenge'));
        }
    }

    public function test_guard_config_returns_default_for_missing_key()
    {
        $this->assertSame('fallback', Laravel2fa::guardConfig('web', 'missing', 'fallback'));
    }

    public function test_unconfigured_guard_throws()
    {
        $this->expectException(InvalidArgumentException::class);

        Laravel2fa::guardConfig('api');
    }

    public function test_resolves_model_and_table_through_auth_config()
    {
        $this->assertSame(User::class, Laravel2fa::modelFor('web'));
        $this->assertSame(Customer::class, Laravel2fa::modelFor('customer'));
        $this->assertSame('users', Laravel2fa::tableFor('web'));
        $this->assertSame('customers', Laravel2fa::tableFor('customer'));
    }

    public function test_resolves_table_for_database_provider()
    {
        config()->set('auth.providers.customers', ['driver' => 'database', 'table' => 'backoffice_users']);

        $this->assertNull(Laravel2fa::modelFor('customer'));
        $this->assertSame('backoffice_users', Laravel2fa::tableFor('customer'));
    }

    public function test_guard_missing_in_auth_config_throws()
    {
        config()->set('laravel-2fa.guards.api', ['prefix' => 'api/two-factor']);

        $this->expectException(InvalidArgumentException::class);

        Laravel2fa::tableFor('api');
    }
}
