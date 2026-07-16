<?php

namespace App\Providers;

use App\Services\BotMessaging\BotMessagingManager;
use App\Services\PhoneVerification\LogPhoneVerificationSender;
use App\Services\PhoneVerification\PhoneVerificationSender;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BotMessagingManager::class);
        $this->app->bind(PhoneVerificationSender::class, function (): PhoneVerificationSender {
            return match (config('phone_verification.driver')) {
                'log' => new LogPhoneVerificationSender,
                default => throw new \LogicException('Unsupported phone verification driver.'),
            };
        });
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
