<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Illuminate\Contracts\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $analyticsService) {}

    public function index(): View
    {
        $metrics = $this->analyticsService->getDashboardMetrics();

        return response()->htmx('admin.analytics.index', 'analytics-content', compact('metrics'));
    }
}
