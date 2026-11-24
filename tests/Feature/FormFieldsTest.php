<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class FormFieldsTest extends TestCase
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

        $router->middleware(['web', 'auth:web'])->get('/profile', function () {
            return Laravel2fa::formFields('web', auth('web')->user());
        });

        $router->middleware(['web', 'auth:web'])->post('/profile', function (Request $request) {
            if ($request->boolean('two_factor_regenerate_codes')) {
                Laravel2fa::regenerateRecoveryCodes($request, $request->user('web'));
            }

            return redirect('/profile');
        });
    }

    public function test_regenerate_checkbox_is_shown_only_to_confirmed_users()
    {
        $this->assertStringNotContainsString('name="two_factor_regenerate_codes"', Laravel2fa::formFields('web', $this->createUser(true, false))->render());
        $this->assertStringContainsString('name="two_factor_regenerate_codes"', Laravel2fa::formFields('web', $this->createCustomer(true))->render());
    }

    public function test_regenerating_from_the_form_shows_the_new_codes_once()
    {
        $user = $this->createUser(true);
        $old = $user->getRecoveryCodes();

        $this->actingAs($user, 'web')
            ->post('/profile', ['two_factor_regenerate_codes' => '1'])
            ->assertRedirect('/profile');

        $codes = $user->fresh()->getRecoveryCodes();
        $this->assertCount(8, $codes);
        $this->assertNotSame($old, $codes);

        $this->get('/profile')->assertSee($codes[0])->assertSee($codes[7]);
        $this->get('/profile')->assertDontSee($codes[0]);
    }

    public function test_saving_without_the_checkbox_keeps_the_codes()
    {
        $user = $this->createUser(true);
        $old = $user->getRecoveryCodes();

        $this->actingAs($user, 'web')->post('/profile');

        $this->assertSame($old, $user->fresh()->getRecoveryCodes());
        $this->get('/profile')->assertDontSee($old[0]);
    }

    public function test_unconfirmed_users_cannot_regenerate_codes()
    {
        $user = $this->createUser(true, false);

        $this->actingAs($user, 'web')
            ->post('/profile', ['two_factor_regenerate_codes' => '1'])
            ->assertSessionMissing('laravel-2fa.recovery_codes');

        $this->assertSame([], $user->fresh()->getRecoveryCodes());
    }

    public function test_fields_have_no_hidden_input()
    {
        $html = Laravel2fa::formFields('web', $this->createUser(true))->render();

        $this->assertStringNotContainsString('type="hidden"', $html);
    }

    public function test_unchecked_checkbox_stays_unchecked_with_old_input()
    {
        $user = $this->createUser(true);

        $html = $this->actingAs($user, 'web')
            ->withSession(['_old_input' => ['two_factor_method' => 'email']])
            ->get('/profile')
            ->getContent();

        $this->assertMatchesRegularExpression('/name="two_factor_enabled" value="1"\s*>/', $html);
        $this->assertStringContainsString('<option value="email" selected>', $html);
        $this->assertMatchesRegularExpression('/<option value="totp"\s*>/', $html);
    }

    public function test_checked_checkbox_stays_checked_with_old_input()
    {
        $user = $this->createUser();

        $html = $this->actingAs($user, 'web')
            ->withSession(['_old_input' => ['two_factor_enabled' => '1', 'two_factor_method' => 'totp']])
            ->get('/profile')
            ->getContent();

        $this->assertStringContainsString('name="two_factor_enabled" value="1" checked>', $html);
        $this->assertStringContainsString('<option value="totp" selected>', $html);
    }

    public function test_stored_values_are_used_without_old_input()
    {
        $user = $this->createUser(true);

        $html = $this->actingAs($user, 'web')->get('/profile')->getContent();

        $this->assertStringContainsString('name="two_factor_enabled" value="1" checked>', $html);
        $this->assertStringContainsString('<option value="totp" selected>', $html);
    }

    public function test_disabled_user_gets_unchecked_fields()
    {
        $html = Laravel2fa::formFields('web', $this->createUser())->render();

        $this->assertMatchesRegularExpression('/name="two_factor_enabled" value="1"\s*>/', $html);
        $this->assertStringContainsString('name="two_factor_method"', $html);
        $this->assertMatchesRegularExpression('/<option value="totp"\s*>/', $html);
        $this->assertMatchesRegularExpression('/<option value="email"\s*>/', $html);
    }

    public function test_enabled_user_gets_checked_fields()
    {
        $html = Laravel2fa::formFields('web', $this->createUser(true))->render();

        $this->assertStringContainsString('name="two_factor_enabled" value="1" checked>', $html);
        $this->assertStringContainsString('<option value="totp" selected>', $html);
    }

    public function test_email_option_requires_an_email_address()
    {
        $user = $this->createUser();
        $user->forceFill(['email' => ''])->save();

        $html = Laravel2fa::formFields('web', $user->fresh())->render();

        $this->assertMatchesRegularExpression('/<option value="totp"\s*>/', $html);
        $this->assertStringNotContainsString('<option value="email"', $html);
    }

    public function test_fields_are_rendered_unescaped_in_blade()
    {
        $html = Blade::render('{{ Laravel2fa::formFields(\'web\', $user) }}', ['user' => $this->createUser()]);

        $this->assertStringContainsString('<select id="l2fa-method-web" name="two_factor_method">', $html);
    }

    public function test_guard_can_use_its_own_view()
    {
        config()->set('laravel-2fa.guards.customer.methods', ['totp']);
        config()->set('laravel-2fa.guards.customer.views.fields', 'testing::fields');

        $html = Laravel2fa::formFields('customer', $this->createCustomer(true))->render();

        $this->assertSame('fields:customer:totp:totp', trim($html));
    }
}
