<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::post('/test-headers', function (Request $request) {
    Log::info('Test headers:', $request->headers->all());
    return response()->json(['ok' => true]);
});
