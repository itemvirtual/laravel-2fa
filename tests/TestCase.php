<?php

namespace Itemvirtual\Laravel2fa\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Laravel2faServiceProvider;
use Itemvirtual\Laravel2fa\Tests\Fixtures\Customer;
use Itemvirtual\Laravel2fa\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [Laravel2faServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Laravel2fa' => \Itemvirtual\Laravel2fa\Laravel2faFacade::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.guards.customer', ['driver' => 'session', 'provider' => 'customers']);
        $app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
        $app['config']->set('auth.providers.customers', ['driver' => 'eloquent', 'model' => Customer::class]);

        $app['config']->set('laravel-2fa.guards.customer', [
            'prefix' => 'customer/two-factor',
            'middleware' => ['web'],
            'login_route' => 'customer.login',
            'redirect' => '/customer/dashboard',
            'views' => [
                'challenge' => 'laravel-2fa::challenge',
                'setup' => 'laravel-2fa::setup',
            ],
        ]);

        $app['view']->addNamespace('testing', __DIR__ . '/Fixtures/views');

        foreach (['web', 'customer'] as $guard) {
            $app['config']->set('laravel-2fa.guards.' . $guard . '.views', [
                'challenge' => 'testing::challenge',
                'setup' => 'testing::setup',
            ]);
        }
    }

    protected function defineRoutes($router)
    {
        $router->middleware('web')->group(function ($router) {
            $router->get('/login', function () {
                return 'login';
            })->name('login');

            $router->get('/customer/login', function () {
                return 'customer-login';
            })->name('customer.login');

            $router->get('/dashboard', function () {
                return 'dashboard';
            })->middleware(['auth:web', 'laravel-2fa.enforced:web']);

            $router->get('/customer/dashboard', function () {
                return 'customer-dashboard';
            })->middleware(['auth:customer', 'laravel-2fa.enforced:customer']);
        });
    }

    protected function removeGuard(string $guard): void
    {
        $guards = config('laravel-2fa.guards');
        unset($guards[$guard]);

        config()->set('laravel-2fa.guards', $guards);
    }

    protected function createGuardTables(): void
    {
        foreach (['users', 'customers'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('email')->unique();
                $blueprint->string('password');
                $blueprint->rememberToken();
                $blueprint->timestamps();
            });
        }
    }

    protected function twoFactorMigration(): Migration
    {
        return require __DIR__ . '/../database/migrations/add_two_factor_columns.php.stub';
    }

    protected function setUpTwoFactorTables(): void
    {
        $this->createGuardTables();
        $this->twoFactorMigration()->up();
    }

    protected function createUser(bool $twoFactor = false, bool $confirmed = true): User
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret', 'two_factor_enabled' => $twoFactor]);

        if ($twoFactor && $confirmed) {
            $this->confirmTwoFactor($user);
        }

        return $user->fresh();
    }

    protected function createCustomer(bool $twoFactor = false, bool $confirmed = true): Customer
    {
        $customer = Customer::create(['name' => 'Joe', 'email' => 'joe@example.com', 'password' => 'secret', 'two_factor_enabled' => $twoFactor]);

        if ($twoFactor && $confirmed) {
            $this->confirmTwoFactor($customer);
        }

        return $customer->fresh();
    }

    protected function confirmTwoFactor(TwoFactorAuthenticatable $user): void
    {
        $user->startTwoFactorSetup(Laravel2fa::generateSecret());
        $user->confirmTwoFactor(Laravel2fa::generateRecoveryCodes());
    }

    protected function currentCode(TwoFactorAuthenticatable $user): string
    {
        return Laravel2fa::google2fa()->getCurrentOtp($user->fresh()->getTwoFactorSecret());
    }
}
