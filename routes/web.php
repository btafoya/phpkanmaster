<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

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

// Public pages
Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');
