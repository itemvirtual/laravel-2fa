<?php

namespace Itemvirtual\Laravel2fa;

use Illuminate\Support\ServiceProvider;

class Laravel2faServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        $this->loadViewsFrom([$this->publishedViewsPath(), __DIR__ . '/../resources/views'], 'laravel-2fa');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laravel-2fa');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('laravel-2fa.php'),
            ], 'config');

            // Publishing the views.
            $this->publishes([
                __DIR__ . '/../resources/views' => $this->publishedViewsPath(),
            ], 'views');

            // Publishing the migration.
            $this->publishes([
                __DIR__ . '/../database/migrations/add_two_factor_columns.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_add_two_factor_columns.php'),
            ], 'migrations');

            // add a logging channel
            if (config('laravel-2fa.logging', null) && config('laravel-2fa.save_logs', null)) {
                $this->app->make('config')->set('logging.channels.laravel-2fa', config('laravel-2fa.logging'));
            }

            // Registering package commands.
            $this->commands([
                Console\Commands\DoctrineMappings::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // This will allow your users to define only the options they actually
        // want to override in the published copy of the configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'laravel-2fa');

        // Register the main class to use with the facade
        $this->app->singleton('laravel-2fa', function () {
            return new Laravel2fa;
        });
    }

    private function publishedViewsPath(): string
    {
        return resource_path('views/' . trim((string)config('laravel-2fa.views_path', 'laravel-2fa'), '/'));
    }
}
