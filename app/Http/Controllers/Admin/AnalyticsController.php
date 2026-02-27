<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $analyticsService) {}

    public function index()
    {
        $metrics = $this->analyticsService->getDashboardMetrics();

        return response()->htmx('admin.analytics.index', 'analytics-content', compact('metrics'));
    }

    public function snapshot(Request $request): JsonResponse
    {
        $isTokenAuthenticated = $request->attributes->get('analytics_token_authenticated') === true;

        if ($isTokenAuthenticated && $request->boolean('fresh')) {
            return $this->snapshotErrorResponse(
                code: 'fresh_not_allowed_for_token',
                message: 'Query parameter fresh=1 is not allowed for token-authenticated callers.',
                status: 422
            );
        }

        $payload = $this->buildLivePayload(
            fresh: $isTokenAuthenticated ? false : $request->boolean('fresh')
        );

        $response = response()->json($payload);

        if ($isTokenAuthenticated) {
            $snapshotId = sprintf(
                '%s@%s',
                $payload['audit_week']['iso_week'],
                $payload['snapshot_generated_at']
            );

            $response->header('X-Analytics-Snapshot-Id', $snapshotId);
        }

        return $response;
    }

    /**
     * @return array{
     *     schema_version: string,
     *     snapshot_generated_at: string,
     *     audit_week: array{timezone: string, iso_week: string, week_start: string, week_end: string},
     *     metrics: array<string, mixed>
     * }
     */
    private function buildLivePayload(bool $fresh): array
    {
        $metrics = $this->analyticsService->getDashboardMetrics($fresh);
        $timezone = (string) config('analytics.snapshot_timezone', 'America/New_York');
        $now = now($timezone);

        $weekStart = $now->copy()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        if (($metrics['generated_at'] ?? null) instanceof DateTimeInterface) {
            $metrics['generated_at'] = $metrics['generated_at']->format(DATE_ATOM);
        }

        return [
            'schema_version' => (string) config('analytics.schema_version', 'admin_analytics_weekly_v1'),
            'snapshot_generated_at' => $now->format(DATE_ATOM),
            'audit_week' => [
                'timezone' => $timezone,
                'iso_week' => $now->format('o-\WW'),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],
            'metrics' => $metrics,
        ];
    }

    private function snapshotErrorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
