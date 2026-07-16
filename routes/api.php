<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use Illuminate\Support\Facades\Route;

(require __DIR__.'/../app/Domain/Market/Routes/route.php')();
(require __DIR__.'/../app/Domain/Asset/Routes/route.php')();

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
        Route::put('phone', [PhoneVerificationController::class, 'updatePhone']);
        Route::post('phone/verification-code', [PhoneVerificationController::class, 'sendCode'])
            ->middleware('throttle:5,1');
        Route::post('phone/verify', [PhoneVerificationController::class, 'verify'])
            ->middleware('throttle:10,1');
    });
});
