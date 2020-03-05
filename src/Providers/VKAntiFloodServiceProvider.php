<?php

namespace SSV\VKAntiFlood\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use SSV\VKAntiFlood\Repositories\VKAntiFloodRepository;


class VKAntiFloodServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(VKAntiFloodRepository::class, function (Application $app) {
            return new VKAntiFloodRepository();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
