<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasskeyLoginController;
use App\Http\Controllers\PasskeyRegistrationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.token');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::post('/passkeys/options', [PasskeyLoginController::class, 'options'])
        ->middleware('throttle:passkeyLogin')
        ->name('passkeys.options');
    Route::post('/passkeys/login', [PasskeyLoginController::class, 'login'])
        ->middleware('throttle:passkeyLogin')
        ->name('passkeys.login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () { return redirect()->route('dashboard'); });
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::post('/passkeys/register/options', [PasskeyRegistrationController::class, 'options'])
        ->middleware('throttle:passkeyRegistration')
        ->name('passkeys.register.options');
    Route::post('/passkeys/register', [PasskeyRegistrationController::class, 'store'])
        ->middleware('throttle:passkeyRegistration')
        ->name('passkeys.register');

    Route::post('/analyze', [BusinessCardController::class, 'analyze'])->name('cards.analyze');
    Route::post('/notion', [BusinessCardController::class, 'pushToNotion'])->name('cards.notion');
    Route::post('/clear', [BusinessCardController::class, 'clear'])->name('cards.clear');

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
