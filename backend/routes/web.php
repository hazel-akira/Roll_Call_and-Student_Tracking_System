<?php

use App\Http\Controllers\Auth\MicrosoftOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Roll Call and Student Tracking API',
        'status' => 'ok',
        'version' => 'v1',
        'panels' => [
            'admin' => url('/admin'),
            'teacher' => url('/teacher'),
        ],
        'microsoft_sso' => config('services.microsoft.enabled') && filled(config('services.microsoft.client_id')),
    ]);
});

Route::middleware('web')->group(function (): void {
    Route::get('/auth/microsoft/redirect', [MicrosoftOAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftOAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
});
