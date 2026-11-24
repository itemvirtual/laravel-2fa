<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class RouteRegistrationTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $guards = $app['config']->get('laravel-2fa.guards');
        unset($guards['customer']);
        $app['config']->set('laravel-2fa.guards', $guards);
    }

    public function test_configured_guard_registers_its_routes()
    {
        foreach (['challenge', 'challenge.store', 'challenge.resend', 'recovery-codes.download'] as $name) {
            $this->assertTrue(Route::has('laravel-2fa.web.' . $name), $name);
        }

        foreach (['setup', 'setup.store', 'setup.destroy', 'setup.method', 'setup.resend', 'recovery-codes'] as $name) {
            $this->assertFalse(Route::has('laravel-2fa.web.' . $name), $name);
        }

        $this->assertSame(url('/two-factor/challenge'), route('laravel-2fa.web.challenge'));
    }

    public function test_guard_not_configured_registers_no_routes()
    {
        foreach (['challenge', 'challenge.store', 'challenge.resend', 'recovery-codes.download'] as $name) {
            $this->assertFalse(Route::has('laravel-2fa.customer.' . $name), $name);
        }

        $this->get('/customer/two-factor/challenge')->assertNotFound();
    }

    public function test_routes_receive_the_guard_as_default()
    {
        $this->assertSame('web', Route::getRoutes()->getByName('laravel-2fa.web.recovery-codes.download')->defaults['guard']);
    }
}
