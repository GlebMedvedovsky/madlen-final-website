<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Http\Middleware\AuthenticateSession;

class AuthenticateAdminSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        // A genuine login stamps the session via Laravel's Login event. Do not
        // silently bless an old unstamped session with the NEW password hash.
        if ($request->hasSession() && $request->user()
            && ! $this->guard()->viaRemember()
            && ! $request->session()->has('password_hash_'.$this->auth->getDefaultDriver())) {
            $this->logout($request);
        }

        return parent::handle($request, $next);
    }

    protected function redirectTo($request): ?string
    {
        return route('filament.admin.auth.login');
    }
}
