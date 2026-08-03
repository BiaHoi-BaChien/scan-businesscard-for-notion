<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$wFHnG3cvbG.y42CIRt1AP.YvX9F1xg.ghdRGYZMBoFtQwdocKbfIi';

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
        $passwordHash = $user?->password ?? self::DUMMY_PASSWORD_HASH;
        $passwordValid = Hash::check($credentials['password'], $passwordHash);

        if (! $user || ! $passwordValid) {
            return back()
                ->withErrors(['username' => '認証に失敗しました'])
                ->withInput($request->only('username'));
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
