<?php

use App\Domain\Market\Controllers\MarketProviderController;
use App\Domain\Market\Controllers\PublicQuoteController;
use App\Domain\Market\Controllers\ProviderMarketController;
use App\Domain\Market\Controllers\TestController;
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
            // Before the resource so it isn't captured by providers/{provider}
            Route::get('providers/drivers', [MarketProviderController::class, 'drivers']);
            Route::apiResource('providers', MarketProviderController::class);
            Route::apiResource('provider-markets', ProviderMarketController::class);
        });
    });
};
