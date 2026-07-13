<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

(require __DIR__.'/../app/Domain/Market/Routes/route.php')();
(require __DIR__.'/../app/Domain/Asset/Routes/route.php')();

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
