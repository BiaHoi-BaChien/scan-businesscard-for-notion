<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasskeyController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'passkey' => 'required|string|min:6',
        ]);

        $user = Auth::user();
        $user->update([
            'passkey_hash' => Hash::make($data['passkey']),
            'passkey_registered_at' => now(),
        ]);

        return back()->with('status', 'パスキーを更新しました');
    }
}
