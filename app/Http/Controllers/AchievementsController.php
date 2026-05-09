<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\Request;

class AchievementsController extends Controller
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function index(Request $request)
    {
        $shelf = $this->achievementService->getShelfData($request->user());
        $categoryLabels = config('achievements.categories', []);

        return response()->htmx('achievements.index', 'content', compact('shelf', 'categoryLabels'));
    }
}
