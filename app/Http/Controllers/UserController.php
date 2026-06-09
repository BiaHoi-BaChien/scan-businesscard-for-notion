<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('username')->get();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:8',
            'is_admin' => 'boolean',
        ]);

        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return back()->with('status', 'ユーザーを追加しました');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => 'nullable|string|min:8',
            'is_admin' => 'boolean',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return back()->with('status', 'ユーザー情報を更新しました');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => '自分自身を削除することはできません。']);
        }

        $user->delete();

        return back()->with('status', 'ユーザーを削除しました');
    }
}
