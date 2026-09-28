<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        // actingAs bypasses the Login event; seed the snapshot of a real login.
        return $this->withSession([
            'password_hash_'.($guard ?? Auth::getDefaultDriver()) => Auth::guard($guard)->hashPasswordForCookie($user->getAuthPassword()),
        ]);
    }
}
