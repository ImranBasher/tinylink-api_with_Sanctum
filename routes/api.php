<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UrlController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/urls', [UrlController::class, 'store']);
    Route::get('/urls', [UrlController::class, 'index']);
    Route::get('/urls/{id}/stats', [UrlController::class, 'stats'])->whereNumber('id');
    Route::get('/urls/{id}', [UrlController::class, 'show'])->whereNumber('id');
    Route::delete('/urls/{id}', [UrlController::class, 'destroy'])->whereNumber('id');
});
