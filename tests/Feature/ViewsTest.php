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
                'setup' => 'laravel-2fa::setup',
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
            ->assertSee(__('The provided two factor recovery code was invalid.'));
    }

    public function test_setup_view_when_pending()
    {
        $user = $this->createUser(true, false);

        $response = $this->actingAs($user, 'web')->get('/two-factor/setup')->assertOk();

        $response->assertSee('<svg', false)
            ->assertDontSee('<?xml', false)
            ->assertSee($user->fresh()->getTwoFactorSecret())
            ->assertSee('action="' . url('/two-factor/setup') . '"', false);
    }

    public function test_setup_view_when_confirmed_shows_codes_once()
    {
        $user = $this->createUser(true, false);
        $this->actingAs($user, 'web')->get('/two-factor/setup');
        $this->post('/two-factor/setup', ['code' => $this->currentCode($user)]);
        $codes = $user->fresh()->getRecoveryCodes();

        $this->get('/two-factor/setup')
            ->assertSee(__('Two factor authentication has been enabled.'))
            ->assertSee($codes[0])
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee("window.confirm('Are you sure you want to reset two factor authentication?')", false)
            ->assertSee('href="' . url('/dashboard') . '"', false);

        $this->get('/two-factor/setup')->assertDontSee($codes[0]);
    }

    public function test_views_follow_the_javascript_conventions()
    {
        foreach (File::files(__DIR__ . '/../../resources/views') as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString('=>', $contents, $file->getFilename());
            $this->assertStringNotContainsString('`', $contents, $file->getFilename());
        }
    }

    public function test_views_are_configurable_per_guard()
    {
        config()->set('laravel-2fa.guards.customer.views.setup', 'testing::setup');
        $customer = $this->createCustomer(true, false);

        $this->actingAs($customer, 'customer')->get('/customer/two-factor/setup')->assertSee('setup:customer:pending');
        $this->actingAs($this->createUser(true, false), 'web')->get('/two-factor/setup')->assertSee('<svg', false);
    }
}
