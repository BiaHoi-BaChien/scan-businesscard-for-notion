<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () { return redirect()->route('dashboard'); });
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/passkey', [PasskeyController::class, 'update'])->name('passkey.update');

    Route::post('/upload', [BusinessCardController::class, 'upload'])->name('cards.upload');
    Route::post('/analyze', [BusinessCardController::class, 'analyze'])->name('cards.analyze');
    Route::post('/notion', [BusinessCardController::class, 'pushToNotion'])->name('cards.notion');

    Route::middleware(fn ($request, $next) => auth()->user()?->is_admin ? $next($request) : abort(403))->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
