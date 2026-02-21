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

        $configuredToken = (string) config('analytics.export_token', '');
        $providedToken = (string) $request->header('X-Analytics-Token', '');

        if ($configuredToken !== '' && $providedToken !== '' && hash_equals($configuredToken, $providedToken)) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
