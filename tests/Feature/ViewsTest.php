<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\File;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class ViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTwoFactorTables();

        foreach (['web', 'customer'] as $guard) {
            config()->set('laravel-2fa.guards.' . $guard . '.views', [
                'challenge' => 'laravel-2fa::challenge',
            ]);
        }
    }

    public function test_challenge_view()
    {
        $user = $this->createUser(true);

        $this->withSession(['laravel-2fa.web.pending' => ['id' => $user->id, 'remember' => false]])
            ->get('/two-factor/challenge')
            ->assertOk()
            ->assertSee('action="' . url('/two-factor/challenge') . '"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="code"', false)
            ->assertSee('name="recovery_code"', false)
            ->assertSee('data-mode="code"', false)
            ->assertSee('function l2faSetMode(mode)', false);
    }

    public function test_challenge_view_starts_in_recovery_mode_after_a_recovery_error()
    {
        $user = $this->createUser(true);
        $session = ['laravel-2fa.web.pending' => ['id' => $user->id, 'remember' => false]];

        $this->withSession($session)
            ->from('/two-factor/challenge')
            ->post('/two-factor/challenge', ['recovery_code' => 'wrong']);

        $this->get('/two-factor/challenge')
            ->assertSee('data-mode="recovery"', false)
            ->assertSee(trans('laravel-2fa::laravel-2fa.invalid_recovery_code'));
    }

    public function test_email_challenge_view()
    {
        config()->set('laravel-2fa.guards.web.methods', ['totp', 'email']);
        $user = $this->createUser(true, false);
        $user->startTwoFactorSetup(null, 'email');
        $user->confirmTwoFactor(['code-1']);

        $this->withSession(['laravel-2fa.web.pending' => ['id' => $user->id, 'remember' => false]])
            ->get('/two-factor/challenge')
            ->assertSee('j***@example.com')
            ->assertSee('action="' . url('/two-factor/challenge/resend') . '"', false);
    }

    public function test_views_follow_the_javascript_conventions()
    {
        foreach (File::allFiles(__DIR__ . '/../../resources/views') as $file) {
            preg_match_all('/<script>(.*?)<\/script>/s', File::get($file->getPathname()), $scripts);

            foreach ($scripts[1] as $script) {
                $this->assertStringNotContainsString('=>', $script, $file->getFilename());
                $this->assertStringNotContainsString('`', $script, $file->getFilename());
            }
        }
    }

    public function test_views_are_configurable_per_guard()
    {
        config()->set('laravel-2fa.guards.customer.views.challenge', 'testing::challenge');
        $customer = $this->createCustomer(true);
        $user = $this->createUser(true);

        $this->withSession(['laravel-2fa.customer.pending' => ['id' => $customer->id, 'remember' => false]])
            ->get('/customer/two-factor/challenge')
            ->assertSee('challenge:customer:');

        $this->withSession(['laravel-2fa.web.pending' => ['id' => $user->id, 'remember' => false]])
            ->get('/two-factor/challenge')
            ->assertSee('name="code"', false);
    }
}
