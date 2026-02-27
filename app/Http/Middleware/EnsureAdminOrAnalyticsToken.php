<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrAnalyticsToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        $providedToken = $request->bearerToken();
        $configuredToken = (string) config('analytics.export_token', '');

        if (is_string($providedToken) && $providedToken !== '' && $configuredToken !== '' && hash_equals($configuredToken, $providedToken)) {
            $request->attributes->set('analytics_token_authenticated', true);

            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
