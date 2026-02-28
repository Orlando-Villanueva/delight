<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
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

        $payload = $this->analyticsService->buildSnapshotPayload(
            fresh: $isTokenAuthenticated ? false : $request->boolean('fresh')
        );

        $response = response()->json($payload);

        if ($isTokenAuthenticated) {
            $snapshotVersion = (string) ($payload['metrics']['generated_at'] ?? $payload['snapshot_generated_at']);

            $snapshotId = sprintf(
                '%s@%s',
                $payload['audit_week']['iso_week'],
                $snapshotVersion
            );

            $response->header('X-Analytics-Snapshot-Id', $snapshotId);
        }

        return $response;
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
