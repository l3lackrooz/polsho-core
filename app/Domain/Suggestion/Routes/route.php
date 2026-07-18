<?php

use App\Domain\Suggestion\Controllers\SuggestionAdminController;
use App\Domain\Suggestion\Controllers\SuggestionController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Returned as a closure (see the Market domain route file) so registration
// happens inside the router's /api group instead of at autoload time.
return function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        // App-facing: a user submits requests and tracks their own.
        Route::prefix('suggestions')->group(function (): void {
            Route::get('/', [SuggestionController::class, 'index']);
            Route::post('/', [SuggestionController::class, 'store']);
        });

        // Backoffice triage queue.
        Route::prefix('backoffice/suggestions')
            ->middleware(EnsureAdmin::class)
            ->group(function (): void {
                Route::get('/', [SuggestionAdminController::class, 'index']);
                Route::get('{suggestion}', [SuggestionAdminController::class, 'show']);
                Route::match(['put', 'patch'], '{suggestion}', [SuggestionAdminController::class, 'update']);
                Route::delete('{suggestion}', [SuggestionAdminController::class, 'destroy']);
            });
    });
};
