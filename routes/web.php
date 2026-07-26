<?php

use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('password/reset/{token}', [PasswordResetController::class, 'create'])
    ->middleware('guest')
    ->name('password.reset');
Route::post('password/reset', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.update');
