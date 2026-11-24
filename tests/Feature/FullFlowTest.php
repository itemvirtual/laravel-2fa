<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class FullFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    protected function defineRoutes($router)
    {
        parent::defineRoutes($router);

        $router->middleware('web')->group(function ($router) {
            foreach (['web' => '', 'customer' => '/customer'] as $guard => $prefix) {
                $router->post($prefix . '/login', function (Request $request) use ($guard) {
                    if (!Auth::guard($guard)->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
                        return 'failed';
                    }

                    if ($redirect = Laravel2fa::challengeIfRequired($request, $guard)) {
                        return $redirect;
                    }

                    $request->session()->regenerate();

                    return redirect()->intended(Laravel2fa::guardConfig($guard, 'redirect'));
                });

                $router->post($prefix . '/logout', function (Request $request) use ($guard) {
                    Auth::guard($guard)->logout();
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();

                    return 'logged-out';
                });
            }
        });
    }

    private function withPassword(TwoFactorAuthenticatable $user): TwoFactorAuthenticatable
    {
        $user->forceFill(['password' => Hash::make('secret')])->save();

        return $user;
    }

    public function test_full_flow_on_both_guards_in_the_same_session()
    {
        $user = $this->withPassword($this->createUser(true));
        $customer = $this->withPassword($this->createCustomer(true));

        $this->post('/customer/login', ['email' => 'joe@example.com', 'password' => 'secret'])
            ->assertRedirect('/customer/two-factor/challenge');
        $this->assertGuest('customer');

        $this->post('/customer/two-factor/challenge', ['code' => $this->currentCode($customer)])
            ->assertRedirect('/customer/dashboard');
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertRedirect('/two-factor/challenge');
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post('/two-factor/challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get('/dashboard')->assertOk();
        $this->get('/customer/dashboard')->assertOk();
    }

    public function test_pending_customer_challenge_does_not_affect_web()
    {
        $user = $this->withPassword($this->createUser());
        $this->withPassword($this->createCustomer(true));

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret'])->assertRedirect('/dashboard');
        $this->post('/customer/login', ['email' => 'joe@example.com', 'password' => 'secret'])
            ->assertRedirect('/customer/two-factor/challenge');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('customer');
        $this->assertFalse(session()->has('laravel-2fa.web.pending'));
        $this->get('/dashboard')->assertOk();
    }

    public function test_user_without_flag_logs_in_directly()
    {
        $user = $this->withPassword($this->createUser());

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_flagged_user_without_configuration_logs_in_directly()
    {
        $user = $this->withPassword($this->createUser(true, false));

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_logging_out_one_guard_keeps_the_other()
    {
        $user = $this->withPassword($this->createUser());
        $customer = $this->withPassword($this->createCustomer());

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret']);
        $this->post('/customer/login', ['email' => 'joe@example.com', 'password' => 'secret']);

        $this->post('/customer/logout')->assertSee('logged-out');

        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($user, 'web');
        $this->get('/dashboard')->assertOk();
    }

    public function test_remember_me_survives_the_challenge()
    {
        $user = $this->withPassword($this->createUser(true));

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret', 'remember' => '1'])
            ->assertRedirect('/two-factor/challenge');

        $this->post('/two-factor/challenge', ['code' => $this->currentCode($user)])
            ->assertCookie(Auth::guard('web')->getRecallerName());

        $this->assertNotNull($user->fresh()->getRememberToken());
    }
}
