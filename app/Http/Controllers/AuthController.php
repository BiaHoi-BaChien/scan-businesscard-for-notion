<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user) {
            if (config('app.debug')) {
                Log::warning('Login failed: user not found', ['username' => $credentials['username']]);
            }

            return back()->withErrors(['username' => 'ユーザーが見つかりませんでした']);
        }

        $passwordProvided = $credentials['password'] ?? null;

        if (! $passwordProvided || ! Hash::check($passwordProvided, $user->password)) {
            if (config('app.debug')) {
                Log::warning('Login failed: password mismatch', ['username' => $credentials['username']]);
            }

            return back()->withErrors(['password' => '認証に失敗しました'])->withInput();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.form');
    }
}
