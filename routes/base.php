<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::view('/about', 'legal.about')
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('legal.about');

Route::view('/privacy', 'legal.privacy')
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('legal.privacy');

Route::get('/cloud/s/{token}', [Base\CloudFileController::class, 'share'])->where('token', '[A-Za-z0-9]{40}')->middleware('auth')->name('cloud.share');
Route::get('/cloud/files/{uuid}/download', [Base\CloudFileController::class, 'download'])->whereUuid('uuid')->middleware('auth')->name('cloud.download');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon|oauth)).+');
