<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Billing;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::post('/billing/gateways/fiuu/callback', [Billing\FiuuGatewayController::class, 'callback'])
    ->withoutMiddleware(['auth.session', RequireTwoFactorAuthentication::class])
    ->name('billing.gateway.fiuu.callback');

Route::get('/billing/gateways/fiuu/return', [Billing\FiuuGatewayController::class, 'return'])
    ->withoutMiddleware(['auth.session', RequireTwoFactorAuthentication::class])
    ->name('billing.gateway.fiuu.return');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon|oauth)).+');
