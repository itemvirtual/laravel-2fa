<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class UpdateSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
        Mail::fake();

        config()->set('laravel-2fa.guards.web.methods', ['totp', 'email']);
    }

    protected function defineRoutes($router)
    {
        parent::defineRoutes($router);

        $router->middleware(['web', 'auth:web'])->post('/profile', function (Request $request) {
            Laravel2fa::updateSettings($request, 'web', $request->user('web'), $request->boolean('enabled'), $request->input('method'));

            return 'saved';
        });
    }

    public function test_users_start_with_two_factor_disabled()
    {
        $user = $this->createUser();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->getTwoFactorMethod());
    }

    public function test_enabling_totp_leaves_everything_ready()
    {
        $user = $this->createUser();

        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '1', 'method' => 'totp'])->assertOk();

        $user = $user->fresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame('totp', $user->getTwoFactorMethod());
        $this->assertNotNull($user->getTwoFactorSecret());
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertCount(8, $user->getRecoveryCodes());
        $this->assertTrue(Laravel2fa::requiresChallenge('web', $user));
        Mail::assertNothingSent();
    }

    public function test_enabling_email_leaves_everything_ready_without_sending_a_code()
    {
        $user = $this->createUser();

        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '1', 'method' => 'email']);

        $user = $user->fresh();
        $this->assertSame('email', $user->getTwoFactorMethod());
        $this->assertNull($user->getTwoFactorSecret());
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertCount(8, $user->getRecoveryCodes());
        $this->assertTrue(Laravel2fa::requiresChallenge('web', $user));
        Mail::assertNothingSent();
    }

    public function test_next_login_asks_for_the_totp_code()
    {
        $user = $this->createUser();
        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '1', 'method' => 'totp']);
        $user = $user->fresh();

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $this->app['auth']->guard('web')->login($user);

        $this->assertNotNull(Laravel2fa::challengeIfRequired($request, 'web'));
        $this->assertGuest('web');
        $this->assertTrue(Laravel2fa::verify($user, $this->currentCode($user)));
    }

    public function test_saving_the_same_confirmed_method_keeps_the_configuration()
    {
        $user = $this->createUser(true);
        $secret = $user->getTwoFactorSecret();
        $codes = $user->getRecoveryCodes();

        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '1', 'method' => 'totp']);

        $user = $user->fresh();
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertSame($secret, $user->getTwoFactorSecret());
        $this->assertSame($codes, $user->getRecoveryCodes());
    }

    public function test_changing_the_method_regenerates_everything()
    {
        $user = $this->createUser(true);
        $codes = $user->getRecoveryCodes();

        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '1', 'method' => 'email']);

        $user = $user->fresh();
        $this->assertSame('email', $user->getTwoFactorMethod());
        $this->assertNull($user->getTwoFactorSecret());
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertCount(8, $user->getRecoveryCodes());
        $this->assertNotSame($codes, $user->getRecoveryCodes());
        Mail::assertNothingSent();
    }

    public function test_disabling_clears_the_two_factor_data()
    {
        $user = $this->createUser(true);

        $this->actingAs($user, 'web')->post('/profile', ['enabled' => '0']);

        $user = $user->fresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->getTwoFactorMethod());
        $this->assertNull($user->getTwoFactorSecret());
        $this->assertSame([], $user->getRecoveryCodes());
        $this->assertFalse(Laravel2fa::requiresChallenge('web', $user));
    }

    public function test_method_not_allowed_by_the_guard_is_rejected()
    {
        config()->set('laravel-2fa.guards.web.methods', ['totp']);
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        Laravel2fa::updateSettings(Request::create('/profile', 'POST'), 'web', $user, true, 'email');
    }

    public function test_enabling_without_method_is_rejected()
    {
        $user = $this->createUser();

        try {
            Laravel2fa::updateSettings(Request::create('/profile', 'POST'), 'web', $user, true);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        }
    }
}
