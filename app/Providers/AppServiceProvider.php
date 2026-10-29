<?php

namespace App\Providers;

use App\Services\LifestreamApi;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LifestreamApi::class, fn () => new LifestreamApi(
            (string) config('services.lifestream.url'),
            (int) config('services.lifestream.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
