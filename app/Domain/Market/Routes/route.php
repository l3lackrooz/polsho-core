<?php

use App\Domain\Market\Controllers\MarketNotificationController;
use App\Domain\Market\Controllers\MarketProviderController;
use App\Domain\Market\Controllers\PriceAlertController;
use App\Domain\Market\Controllers\PriceAlertNotificationDeliveryController;
use App\Domain\Market\Controllers\ProviderMarketController;
use App\Domain\Market\Controllers\PublicQuoteController;
use App\Domain\Market\Controllers\PushDeviceController;
use App\Domain\Market\Controllers\TestController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Returned as a closure (instead of registering routes at include time) because
// event discovery scans app/Domain and the autoloader includes this file outside
// the router's group stack — direct registration would lose the /api prefix.
return function (): void {
    Route::prefix('pub')->group(function () {
        Route::get('quotes', [PublicQuoteController::class, 'index']);
    });

    Route::prefix('market')->group(function () {
        Route::get('test', [TestController::class, 'index']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::middleware(EnsureAdmin::class)->group(function (): void {
                // Before the resource so it isn't captured by providers/{provider}
                Route::get('providers/drivers', [MarketProviderController::class, 'drivers']);
                Route::apiResource('providers', MarketProviderController::class);
                Route::apiResource('provider-markets', ProviderMarketController::class);
                Route::get('alert-deliveries', [PriceAlertNotificationDeliveryController::class, 'index']);
                Route::post('alert-deliveries/{delivery}/retry', [PriceAlertNotificationDeliveryController::class, 'retry']);
            });

            Route::apiResource('price-alerts', PriceAlertController::class);
            Route::get('notifications', [MarketNotificationController::class, 'index']);
            Route::post('notifications/{notification}/read', [MarketNotificationController::class, 'markRead']);
            Route::post('notifications/read-all', [MarketNotificationController::class, 'markAllRead']);
        });
    });

    Route::prefix('push')->middleware('auth:sanctum')->group(function (): void {
        Route::put('devices/{installationId}', [PushDeviceController::class, 'upsert']);
        Route::delete('devices/{installationId}', [PushDeviceController::class, 'destroy']);
    });
};
