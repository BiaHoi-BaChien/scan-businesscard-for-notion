<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'nullable|string',
            'passkey' => 'nullable|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user) {
            return back()->withErrors(['username' => 'ユーザーが見つかりませんでした']);
        }

        $passwordProvided = $credentials['password'] ?? null;
        $passkeyProvided = $credentials['passkey'] ?? null;

        $authenticated = false;

        if ($passwordProvided && Hash::check($passwordProvided, $user->password)) {
            $authenticated = true;
        }

        if (! $authenticated && $passkeyProvided && $user->passkey_hash && Hash::check($passkeyProvided, $user->passkey_hash)) {
            $authenticated = true;
        }

        if (! $authenticated) {
            return back()->withErrors(['password' => '認証に失敗しました'])->withInput();
        }

        Auth::login($user, true);

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
