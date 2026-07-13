<?php

namespace App\Providers;

use App\Services\BotMessaging\BotMessagingManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BotMessagingManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        // Http::macro('debug', function () {
        // return Http::withOptions([
        //     'debug' => fopen('php://stderr', 'w')
        // ]);
        // });
    }
}
