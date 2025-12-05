<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('username')->get();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $authSecret = $this->getAuthSecretOrFail();

        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:8',
            'is_admin' => 'boolean',
        ]);

        $data['password'] = Hash::make($data['password']);

        if ($authSecret) {
            $data['encrypted_password'] = base64_encode(openssl_encrypt(
                $request->input('password'),
                'AES-256-CBC',
                hash('sha256', $authSecret),
                0,
                substr(hash('sha256', $authSecret), 0, 16)
            ));
        }

        User::create($data);

        return back()->with('status', 'ユーザーを追加しました');
    }

    public function update(Request $request, User $user)
    {
        $authSecret = $this->getAuthSecretOrFail();

        $data = $request->validate([
            'password' => 'nullable|string|min:8',
            'is_admin' => 'boolean',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));

            if ($authSecret) {
                $data['encrypted_password'] = base64_encode(openssl_encrypt(
                    $request->input('password'),
                    'AES-256-CBC',
                    hash('sha256', $authSecret),
                    0,
                    substr(hash('sha256', $authSecret), 0, 16)
                ));
            }
        }

        $user->update($data);

        return back()->with('status', 'ユーザー情報を更新しました');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('status', 'ユーザーを削除しました');
    }

    private function getAuthSecretOrFail(): string
    {
        $authSecret = env('AUTH_SECRET');

        if (! $authSecret) {
            throw ValidationException::withMessages([
                'auth_secret' => 'AUTH_SECRET が設定されていません。',
            ]);
        }

        return $authSecret;
    }
}
