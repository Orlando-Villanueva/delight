<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $analyticsService) {}

    public function index()
    {
        $metrics = $this->analyticsService->getDashboardMetrics();

        return response()->htmx('admin.analytics.index', 'analytics-content', compact('metrics'));
    }

    public function snapshot(): JsonResponse
    {
        $fresh = request()->boolean('fresh');
        $metrics = $this->analyticsService->getDashboardMetrics($fresh);
        $timezone = (string) config('analytics.snapshot_timezone', 'America/New_York');
        $now = now($timezone);

        $weekStart = $now->copy()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        if (($metrics['generated_at'] ?? null) instanceof DateTimeInterface) {
            $metrics['generated_at'] = $metrics['generated_at']->format(DATE_ATOM);
        }

        return response()->json([
            'schema_version' => (string) config('analytics.schema_version', 'admin_analytics_weekly_v1'),
            'snapshot_generated_at' => $now->format(DATE_ATOM),
            'audit_week' => [
                'timezone' => $timezone,
                'iso_week' => $now->format('o-\WW'),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],
            'metrics' => $metrics,
        ]);
    }
}
