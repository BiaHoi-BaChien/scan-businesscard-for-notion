<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/webauthn/login/options', [WebAuthnLoginController::class, 'options'])->name('webauthn.login.options');
    Route::post('/webauthn/login', [WebAuthnLoginController::class, 'login'])->name('webauthn.login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () { return redirect()->route('dashboard'); });
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/webauthn/register/options', [WebAuthnRegisterController::class, 'options'])->name('webauthn.register.options');
    Route::post('/webauthn/register', [WebAuthnRegisterController::class, 'register'])->name('webauthn.register');

    Route::post('/analyze', [BusinessCardController::class, 'analyze'])->name('cards.analyze');
    Route::post('/notion', [BusinessCardController::class, 'pushToNotion'])->name('cards.notion');
    Route::post('/clear', [BusinessCardController::class, 'clear'])->name('cards.clear');

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}/passkey', [UserController::class, 'destroyPasskey'])->name('users.passkey.destroy');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
