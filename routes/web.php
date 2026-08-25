<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Rutas de invitados (no autenticados)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'createForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])->name('password.update');
});

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Rutas de creación de emprendimiento (accesibles aunque el usuario aún no tenga un tenant)
    Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');

    // Rutas que requieren tenant activo
    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::post('/businesses/{business}/switch', [BusinessController::class, 'switch'])->name('businesses.switch');
    });
});
