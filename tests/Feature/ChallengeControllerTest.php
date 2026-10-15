<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class ChallengeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();
    }

    private function pending(string $guard, $id, bool $remember = false): array
    {
        return ['laravel-2fa.' . $guard . '.pending' => ['id' => $id, 'remember' => $remember]];
    }

    public function test_show_without_pending_login_redirects_to_login()
    {
        $this->get('/two-factor/challenge')->assertRedirect('/login');
        $this->get('/customer/two-factor/challenge')->assertRedirect('/customer/login');
    }

    public function test_show_with_pending_login_renders_the_guard_view()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id))
            ->get('/two-factor/challenge')
            ->assertOk()
            ->assertSee('challenge:web:' . url('/two-factor/challenge'));
    }

    public function test_authenticated_user_cannot_see_the_challenge()
    {
        $user = $this->createUser(true);

        $this->actingAs($user, 'web')->get('/two-factor/challenge')->assertRedirect();
    }

    public function test_valid_code_completes_login_on_its_guard()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect('/dashboard')
            ->assertSessionMissing('laravel-2fa.web.pending');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('customer');
    }

    public function test_redirects_to_intended_url()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id) + ['url.intended' => url('/profile')])
            ->post('/two-factor/challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect('/profile');
    }

    public function test_invalid_code_is_rejected()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge', ['code' => '000000'])
            ->assertRedirect('/two-factor/challenge')
            ->assertSessionHasErrors('code')
            ->assertSessionHas('laravel-2fa.web.pending');

        $this->assertGuest('web');
    }

    public function test_replayed_code_is_rejected()
    {
        $user = $this->createUser(true);
        $code = $this->currentCode($user);

        $this->withSession($this->pending('web', $user->id))->post('/two-factor/challenge', ['code' => $code]);
        Auth::guard('web')->logout();

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_empty_submission_is_rejected()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge')
            ->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_once()
    {
        $user = $this->createUser(true);
        $code = $user->getRecoveryCodes()[0];

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge', ['recovery_code' => $code])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user, 'web');

        Auth::guard('web')->logout();

        $this->withSession($this->pending('web', $user->id))
            ->post('/two-factor/challenge', ['recovery_code' => $code])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest('web');
    }

    public function test_remember_is_preserved()
    {
        $user = $this->createUser(true);

        $this->withSession($this->pending('web', $user->id, true))
            ->post('/two-factor/challenge', ['code' => $this->currentCode($user)])
            ->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_customer_challenge_only_logs_in_customer()
    {
        $user = $this->createUser(true);
        $customer = $this->createCustomer(true);

        $this->withSession($this->pending('customer', $customer->id) + $this->pending('web', $user->id))
            ->post('/customer/two-factor/challenge', ['code' => $this->currentCode($customer)])
            ->assertRedirect('/customer/dashboard')
            ->assertSessionHas('laravel-2fa.web.pending');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_post_is_throttled()
    {
        $user = $this->createUser(true);
        $this->withSession($this->pending('web', $user->id));

        for ($i = 0; $i < 6; $i++) {
            $this->post('/two-factor/challenge', ['code' => '000000'])->assertRedirect();
        }

        $this->post('/two-factor/challenge', ['code' => '000000'])->assertStatus(429);
    }
}
