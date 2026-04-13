<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\WebhookController;

// Auth routes (public)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth:single')->group(function () {
    Route::get('/', function () {
        return view('kanban');
    })->name('kanban');
});

// Webhook handler (public — uses its own IP allowlist auth)
Route::post('/webhooks/{source}', [WebhookController::class, 'handle'])
    ->middleware('throttle:60,1');

// Public pages
Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');
