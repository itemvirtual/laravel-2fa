<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Mail\TwoFactorCodeMail;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class EmailMethodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
        Mail::fake();

        config()->set('laravel-2fa.guards.web.methods', ['totp', 'email']);
        config()->set('laravel-2fa.guards.customer.methods', ['totp', 'email']);
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
            }
        });
    }

    private function confirmEmail(TwoFactorAuthenticatable $user): TwoFactorAuthenticatable
    {
        $user->startTwoFactorSetup(null, 'email');
        $user->confirmTwoFactor(Laravel2fa::generateRecoveryCodes());

        return $user->fresh();
    }

    private function lastCode(): string
    {
        $code = null;

        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_email_method_requires_an_email_address()
    {
        $user = $this->createUser(true, false);
        $user->forceFill(['email' => ''])->save();

        $this->assertSame(['totp'], Laravel2fa::availableMethods('web', $user->fresh()));
    }

    public function test_login_sends_the_code_and_completes_with_it()
    {
        $user = $this->confirmEmail($this->createUser(true));

        $this->post('/web/login', ['id' => $user->id])->assertRedirect('/two-factor/challenge');
        $this->assertGuest('web');

        $this->get('/two-factor/challenge')->assertSee('method:email')->assertSee('email:j***@example.com');

        $this->post('/two-factor/challenge', ['code' => $this->lastCode()])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_wrong_code_is_rejected()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);

        $this->post('/two-factor/challenge', ['code' => '00000'])->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_code_is_discarded_after_too_many_attempts()
    {
        config()->set('laravel-2fa.email.max_attempts', 2);
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);
        $code = $this->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        $this->post('/two-factor/challenge', ['code' => $wrong]);
        $this->post('/two-factor/challenge', ['code' => $wrong]);
        $this->post('/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_expired_code_is_rejected()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->post('/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_code_can_be_used_only_once()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);
        $code = $this->lastCode();

        $this->post('/two-factor/challenge', ['code' => $code])->assertRedirect('/dashboard');
        Auth::guard('web')->logout();

        $this->withSession(['laravel-2fa.web.pending' => ['id' => $user->id, 'remember' => false]])
            ->post('/two-factor/challenge', ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_resend_replaces_the_code()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);
        $old = $this->lastCode();

        $this->post('/two-factor/challenge/resend')
            ->assertRedirect('/two-factor/challenge')
            ->assertSessionHas('laravel-2fa.status', 'code-sent');

        Mail::assertSent(TwoFactorCodeMail::class, 2);
        $new = $this->lastCode();

        if ($old !== $new) {
            $this->post('/two-factor/challenge', ['code' => $old])->assertSessionHasErrors('code');
        }

        $this->post('/two-factor/challenge', ['code' => $new])->assertRedirect('/dashboard');
    }

    public function test_resend_is_throttled()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/two-factor/challenge/resend')->assertRedirect();
        }

        $this->post('/two-factor/challenge/resend')->assertStatus(429);
    }

    public function test_throttle_counters_are_independent_per_route()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/two-factor/challenge', ['code' => '00000']);
        }

        $this->post('/two-factor/challenge', ['code' => '00000'])->assertStatus(429);
        $this->post('/two-factor/challenge/resend')->assertSessionHas('laravel-2fa.status', 'code-sent');
    }

    public function test_resend_without_pending_login_redirects_to_login()
    {
        $this->post('/two-factor/challenge/resend')->assertRedirect('/login');

        Mail::assertNothingSent();
    }

    public function test_recovery_code_works_for_email_users()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $this->post('/web/login', ['id' => $user->id]);

        $this->post('/two-factor/challenge', ['recovery_code' => $user->getRecoveryCodes()[0]])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_codes_are_isolated_per_guard()
    {
        $user = $this->confirmEmail($this->createUser(true));
        $customer = $this->confirmEmail($this->createCustomer(true));

        $this->post('/customer/login', ['id' => $customer->id]);
        $customerCode = $this->lastCode();
        $this->post('/web/login', ['id' => $user->id]);

        $this->post('/customer/two-factor/challenge', ['code' => $customerCode])->assertRedirect('/customer/dashboard');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        $this->assertTrue(session()->has('laravel-2fa.web.email'));
    }

    public function test_legacy_totp_users_keep_working_without_method()
    {
        $user = $this->createUser(true);
        $user->forceFill(['two_factor_method' => null])->save();
        $user = $user->fresh();

        $this->assertSame('totp', $user->getTwoFactorMethod());
        $this->assertTrue($user->hasTwoFactorConfirmed());
        $this->assertTrue(Laravel2fa::requiresChallenge('web', $user));
    }

    public function test_mail_contains_the_code()
    {
        $mail = new TwoFactorCodeMail('123456', 10);
        $html = view('laravel-2fa::mail.code', ['code' => $mail->code, 'expires' => $mail->expires])->render();

        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('10', $html);
    }

    public function test_masked_email()
    {
        $this->assertSame('j***@example.com', Laravel2fa::maskEmail('jane@example.com'));
        $this->assertSame('a*@example.com', Laravel2fa::maskEmail('a@example.com'));
        $this->assertSame('', Laravel2fa::maskEmail(null));
    }
}
