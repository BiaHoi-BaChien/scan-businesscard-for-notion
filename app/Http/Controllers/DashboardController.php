<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();
        $latestCard = BusinessCard::where('user_id', $user->id)->latest()->first();

        return view('dashboard', [
            'user' => $user,
            'card' => $latestCard,
        ]);
    }
}
