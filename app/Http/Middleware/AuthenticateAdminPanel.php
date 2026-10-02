<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

class AuthenticateAdminPanel extends Authenticate
{
    /**
     * @param  Request  $request
     */
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
