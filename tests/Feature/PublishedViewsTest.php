<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Itemvirtual\Laravel2fa\Laravel2faServiceProvider;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class PublishedViewsTest extends TestCase
{
    private string $directory = 'laravel-2fa-test-views';

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('laravel-2fa.views_path', $this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('views/' . $this->directory));

        parent::tearDown();
    }

    private function renderChallenge(): string
    {
        return view('laravel-2fa::challenge', ['guard' => 'web', 'action' => '/', 'errors' => new ViewErrorBag()])->render();
    }

    public function test_views_are_published_to_the_configured_directory()
    {
        $views = ServiceProvider::pathsToPublish(Laravel2faServiceProvider::class, 'views');

        $this->assertContains(resource_path('views/' . $this->directory), $views);
    }

    public function test_published_views_override_the_package_views()
    {
        $this->assertStringContainsString('l2faSetMode', $this->renderChallenge());

        File::ensureDirectoryExists(resource_path('views/' . $this->directory));
        File::put(resource_path('views/' . $this->directory . '/challenge.blade.php'), 'published:{{ $guard }}');
        app('view')->getFinder()->flush();

        $this->assertSame('published:web', $this->renderChallenge());
    }

    public function test_partially_published_views_fall_back_to_the_package()
    {
        File::ensureDirectoryExists(resource_path('views/' . $this->directory));
        File::put(resource_path('views/' . $this->directory . '/layout.blade.php'), 'custom-layout @yield(\'content\')');

        $html = $this->renderChallenge();

        $this->assertStringStartsWith('custom-layout', $html);
        $this->assertStringContainsString('name="code"', $html);
    }
}
