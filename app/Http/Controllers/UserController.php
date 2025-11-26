<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function destroyPasskey(User $user)
    {
        try {
            $deletedCredentials = $user->webAuthnCredentials()->delete();

            $user->update([
                'passkey_hash' => null,
                'passkey_registered_at' => null,
            ]);

            Log::info('Removed passkey for user', [
                'user_id' => $user->id,
                'username' => $user->username,
                'deleted_credentials' => $deletedCredentials,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to remove passkey for user', [
                'user_id' => $user->id,
                'username' => $user->username,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['passkey' => 'パスキーの削除に失敗しました。管理者にお問い合わせください。']);
        }

        return back()->with('status', '登録済みのパスキーを削除しました');
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
