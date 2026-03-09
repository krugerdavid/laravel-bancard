<?php

namespace KrugerDavid\Bancard;

use Illuminate\Support\ServiceProvider;

class BancardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bancard.php', 'bancard');

        $this->app->singleton(BancardManager::class, function ($app) {
            return new BancardManager($app['config']['bancard']);
        });

        $this->app->alias(BancardManager::class, 'bancard');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bancard.php' => config_path('bancard.php'),
            ], 'bancard-config');
        }
    }
}
