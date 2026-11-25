<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Itemvirtual\Laravel2fa\Laravel2fa;
use Itemvirtual\Laravel2fa\Laravel2faFacade;
use Itemvirtual\Laravel2fa\Laravel2faServiceProvider;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged()
    {
        $this->assertIsArray(config('laravel-2fa'));
    }

    public function test_manager_is_a_singleton()
    {
        $this->assertInstanceOf(Laravel2fa::class, app('laravel-2fa'));
        $this->assertSame(app('laravel-2fa'), app('laravel-2fa'));
        $this->assertSame(app('laravel-2fa'), Laravel2faFacade::getFacadeRoot());
    }

    public function test_view_namespace_is_registered()
    {
        $this->assertArrayHasKey('laravel-2fa', app('view')->getFinder()->getHints());
    }

    public function test_publish_groups_are_registered()
    {
        $config = ServiceProvider::pathsToPublish(Laravel2faServiceProvider::class, 'config');
        $views = ServiceProvider::pathsToPublish(Laravel2faServiceProvider::class, 'views');
        $migrations = ServiceProvider::pathsToPublish(Laravel2faServiceProvider::class, 'migrations');

        $this->assertContains(config_path('laravel-2fa.php'), $config);
        $this->assertContains(resource_path('views/laravel-2fa'), $views);
        $this->assertCount(1, $migrations);
        $this->assertEmpty(ServiceProvider::pathsToPublish(Laravel2faServiceProvider::class, 'doctrine'));

        foreach (array_merge(array_keys($config), array_keys($views), array_keys($migrations)) as $source) {
            $this->assertFileExists($source);
        }
    }
}
