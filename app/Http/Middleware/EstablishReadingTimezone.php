<?php

namespace App\Http\Middleware;

use App\Services\ReadingCalendarService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablishReadingTimezone
{
    public function __construct(private ReadingCalendarService $readingCalendar) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->reading_timezone === null) {
            $reportedTimezone = $request->header('X-Reading-Timezone') ?? $request->cookie('reading_timezone_report');
            $this->readingCalendar->establishTimezone($user, is_string($reportedTimezone) ? $reportedTimezone : null);
        }

        $response = $next($request);

        if ($user?->reading_timezone !== null && $request->hasCookie('reading_timezone_report')) {
            $response->headers->setCookie(cookie()->forget('reading_timezone_report'));
        }

        return $response;
    }
}
