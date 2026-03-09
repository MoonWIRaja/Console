<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Auth\OAuthController;

Route::middleware('throttle:authentication')->group(function () {
    Route::get('/{provider}/redirect', [OAuthController::class, 'redirect'])->name('auth.oauth.redirect');
    Route::get('/{provider}/callback', [OAuthController::class, 'callback'])->name('auth.oauth.callback');
});
