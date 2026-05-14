<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AchievementsController extends Controller
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function index(Request $request): Response|View
    {
        $shelf = $this->achievementService->getShelfData($request->user());
        $categoryLabels = config('achievements.categories', []);

        return response()->htmx('achievements.index', 'content', compact('shelf', 'categoryLabels'));
    }
}
