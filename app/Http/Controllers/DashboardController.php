<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $passkeys = $request->user()
            ->passkeys()
            ->latest()
            ->get(['id', 'name', 'created_at']);

        return view('dashboard', compact('passkeys'));
    }
}
