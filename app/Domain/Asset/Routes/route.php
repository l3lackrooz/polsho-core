<?php

use App\Domain\Asset\Controllers\AssetController;
use App\Domain\Asset\Controllers\InstrumentController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Returned as a closure (instead of registering routes at include time) because
// event discovery scans app/Domain and the autoloader includes this file outside
// the router's group stack — direct registration would lose the /api prefix.
return function (): void {
    Route::prefix('pub')->group(function () {
        Route::get('instruments', [InstrumentController::class, 'index']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware(EnsureAdmin::class)->group(function (): void {
            Route::post('assets/{asset}/logo', [AssetController::class, 'uploadLogo']);
            Route::delete('assets/{asset}/logo', [AssetController::class, 'deleteLogo']);
            Route::apiResource('assets', AssetController::class);
            Route::apiResource('instruments', InstrumentController::class);
        });
    });
};
