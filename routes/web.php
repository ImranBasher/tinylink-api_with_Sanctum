<?php

use App\Http\Controllers\UrlController;
use Illuminate\Support\Facades\Route;

Route::get('/{short_code}', [UrlController::class, 'redirect'])->name('short.redirect');
