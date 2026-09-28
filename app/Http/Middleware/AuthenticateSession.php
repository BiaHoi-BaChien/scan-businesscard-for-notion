<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;

class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        // Sessions created before password snapshots were introduced must reauthenticate.
        // A fresh remember-cookie login is checked against its password hash by the parent.
        if ($request->hasSession() && $request->user() && ! $this->guard()->viaRemember()
            && ! $request->session()->has('password_hash_'.$this->auth->getDefaultDriver())) {
            $this->logout($request);
        }

        return parent::handle($request, $next);
    }
}
