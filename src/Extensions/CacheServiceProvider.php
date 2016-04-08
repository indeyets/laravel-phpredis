<?php

namespace App\Providers;

class CacheServiceProvider extends \Illuminate\Cache\CacheServiceProvider
{
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
        $this->app->singleton('cache', function ($app) {
            return new \App\Extensions\CacheManager($app);
        });
    }
}
