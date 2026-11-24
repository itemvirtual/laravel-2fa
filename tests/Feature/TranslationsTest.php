<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\File;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class TranslationsTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(lang_path('vendor/laravel-2fa'));

        parent::tearDown();
    }

    public function test_all_locales_have_the_same_keys()
    {
        $keys = array_keys(require __DIR__ . '/../../lang/en/laravel-2fa.php');
        sort($keys);

        foreach (['es', 'ca'] as $locale) {
            $localeKeys = array_keys(require __DIR__ . '/../../lang/' . $locale . '/laravel-2fa.php');
            sort($localeKeys);

            $this->assertSame($keys, $localeKeys, 'Locale [' . $locale . '] keys do not match [en].');
        }
    }

    public function test_texts_are_translated_to_the_current_locale()
    {
        $this->assertSame('Two factor authentication', trans('laravel-2fa::laravel-2fa.two_factor_authentication'));

        app()->setLocale('es');
        $this->assertSame('Autenticación en dos pasos', trans('laravel-2fa::laravel-2fa.two_factor_authentication'));

        app()->setLocale('ca');
        $this->assertSame('Autenticació en dos passos', trans('laravel-2fa::laravel-2fa.two_factor_authentication'));
    }

    public function test_views_use_the_current_locale()
    {
        $this->setUpTwoFactorTables();
        app()->setLocale('es');

        $html = Laravel2fa::formFields('web', $this->createUser())->render();

        $this->assertStringContainsString('Activar la autenticación en dos pasos', $html);
    }

    public function test_project_translations_override_the_package()
    {
        File::ensureDirectoryExists(lang_path('vendor/laravel-2fa/en'));
        File::put(lang_path('vendor/laravel-2fa/en/laravel-2fa.php'), "<?php return ['code' => 'Project code'];");

        $this->assertSame('Project code', trans('laravel-2fa::laravel-2fa.code'));
        $this->assertSame('Recovery code', trans('laravel-2fa::laravel-2fa.recovery_code'));
    }
}
