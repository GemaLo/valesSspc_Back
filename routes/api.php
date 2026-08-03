<?php

use App\Http\Controllers\LoginController;
use App\Http\Middleware\LoginMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware([LoginMiddleware::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/profile', function () {
        return response()->json(['user' => auth()->user()]);
    });
});
