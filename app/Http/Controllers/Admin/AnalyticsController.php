<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $analyticsService) {}

    public function index()
    {
        $metrics = $this->analyticsService->getDashboardMetrics();

        return response()->htmx('admin.analytics.index', 'analytics-content', compact('metrics'));
    }
}
