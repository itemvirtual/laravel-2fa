<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class ManagerTest extends TestCase
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
            foreach (['web', 'customer'] as $guard) {
                $router->post('/' . $guard . '/login', function (Request $request) use ($guard) {
                    Auth::guard($guard)->loginUsingId($request->input('id'), $request->boolean('remember'));

                    return Laravel2fa::challengeIfRequired($request, $guard) ?: 'logged-in';
                });

                $router->post('/' . $guard . '/complete', function (Request $request) use ($guard) {
                    $user = Laravel2fa::completeLogin($request, $guard);

                    return $user ? 'completed' : 'no-pending';
                });
            }
        });
    }

    public function test_config_helpers()
    {
        $this->assertTrue(Laravel2fa::hasGuard('web'));
        $this->assertFalse(Laravel2fa::hasGuard('api'));
        $this->assertSame('laravel-2fa.customer.challenge', Laravel2fa::routeName('customer', 'challenge'));

        $this->removeGuard('web');
        $this->assertFalse(Laravel2fa::hasGuard('web'));
    }

    public function test_requires_challenge()
    {
        $off = $this->createUser();
        $pending = $this->createCustomer(true, false);

        $this->assertFalse(Laravel2fa::requiresChallenge('web', $off));
        $this->assertFalse(Laravel2fa::requiresChallenge('customer', $pending));

        $this->confirmTwoFactor($pending);
        $this->assertTrue(Laravel2fa::requiresChallenge('customer', $pending->fresh()));

        $this->removeGuard('customer');
        $this->assertFalse(Laravel2fa::requiresChallenge('customer', $pending->fresh()));
    }

    public function test_login_without_two_factor_passes_through()
    {
        $user = $this->createUser();

        $this->post('/web/login', ['id' => $user->id])->assertSee('logged-in');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_with_flag_off_passes_through_even_if_configured()
    {
        $user = $this->createUser(true);
        $user->forceFill(['two_factor_enabled' => false])->save();

        $this->post('/web/login', ['id' => $user->id])->assertSee('logged-in');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->getTwoFactorSecret());
    }

    public function test_login_with_flag_on_but_not_configured_passes_through()
    {
        $user = $this->createUser(true, false);

        $this->post('/web/login', ['id' => $user->id])->assertSee('logged-in');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guard_not_configured_passes_through()
    {
        $this->removeGuard('web');
        $user = $this->createUser(true);

        $this->post('/web/login', ['id' => $user->id])->assertSee('logged-in');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_with_two_factor_redirects_to_challenge()
    {
        $user = $this->createUser(true);

        $this->post('/web/login', ['id' => $user->id, 'remember' => '1'])
            ->assertRedirect('/two-factor/challenge')
            ->assertSessionHas('laravel-2fa.web.pending', ['id' => $user->id, 'remember' => true]);

        $this->assertGuest('web');
    }

    public function test_complete_login_authenticates_only_its_guard()
    {
        $user = $this->createUser(true);
        $this->post('/web/login', ['id' => $user->id]);

        $this->post('/web/complete')->assertSee('completed')->assertSessionMissing('laravel-2fa.web.pending');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('customer');
    }

    public function test_complete_login_without_pending_does_nothing()
    {
        $this->post('/web/complete')->assertSee('no-pending');

        $this->assertGuest('web');
    }

    public function test_complete_login_preserves_remember()
    {
        $user = $this->createUser(true);
        $this->post('/web/login', ['id' => $user->id, 'remember' => '1']);

        $this->post('/web/complete')->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_complete_login_without_remember_sets_no_recaller()
    {
        $user = $this->createUser(true);
        $this->post('/web/login', ['id' => $user->id]);

        $this->post('/web/complete')->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_pending_challenges_are_isolated_per_guard()
    {
        $user = $this->createUser(true);
        $customer = $this->createCustomer();

        $this->post('/customer/login', ['id' => $customer->id])->assertSee('logged-in');
        $this->post('/web/login', ['id' => $user->id])->assertRedirect('/two-factor/challenge');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');

        $this->post('/customer/complete')->assertSee('no-pending');
        $this->assertSessionMissingKey('laravel-2fa.customer.pending');
        $this->assertTrue(session()->has('laravel-2fa.web.pending'));
    }

    public function test_verify_accepts_valid_code_and_rejects_replay()
    {
        $user = $this->createUser(true);
        $code = Laravel2fa::google2fa()->getCurrentOtp($user->getTwoFactorSecret());

        $this->assertTrue(Laravel2fa::verify($user, $code));
        $this->assertNotNull($user->fresh()->getTwoFactorLastTimestamp());
        $this->assertFalse(Laravel2fa::verify($user->fresh(), $code));
    }

    public function test_verify_rejects_invalid_codes()
    {
        $user = $this->createUser(true);
        $code = Laravel2fa::google2fa()->getCurrentOtp($user->getTwoFactorSecret());
        $wrong = str_pad((string)(((int)$code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->assertFalse(Laravel2fa::verify($user, $wrong));
        $this->assertFalse(Laravel2fa::verify($user, 'abcdef'));
        $this->assertFalse(Laravel2fa::verify($user, ''));
        $this->assertNull($user->fresh()->getTwoFactorLastTimestamp());
    }

    public function test_verify_accepts_spaces()
    {
        $user = $this->createUser(true);
        $code = Laravel2fa::google2fa()->getCurrentOtp($user->getTwoFactorSecret());

        $this->assertTrue(Laravel2fa::verify($user, substr($code, 0, 3) . ' ' . substr($code, 3)));
    }

    public function test_generators()
    {
        $secret = Laravel2fa::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        config()->set('laravel-2fa.recovery_codes', 5);
        $codes = Laravel2fa::generateRecoveryCodes();
        $this->assertCount(5, $codes);
        $this->assertCount(5, array_unique($codes));
    }

    public function test_qr_code_svg()
    {
        $user = $this->createUser(true);

        $svg = Laravel2fa::qrCodeSvg($user);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringContainsString(rawurlencode('jane@example.com'), Laravel2fa::otpauthUrl($user));
    }

    private function assertSessionMissingKey(string $key): void
    {
        $this->assertFalse(session()->has($key));
    }
}
