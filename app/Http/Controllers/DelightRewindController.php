<?php

namespace App\Http\Controllers;

use App\Services\DelightRewindService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DelightRewindController extends Controller
{
    public function __construct(
        private DelightRewindService $delightRewindService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $year = now()->year;

        // Access Control
        $isDec25OrLater = now()->month === 12 && now()->day >= 25;
        $isLocal = app()->environment('local', 'testing');
        $forceRewind = $request->has('force_rewind');

        if (! $isDec25OrLater && ! $isLocal && ! $forceRewind) {
            abort(404);
        }

        // Calculate Stats
        $stats = $this->delightRewindService->getRewindStats($user, $year);

        return view('delight-rewind.index', [
            'stats' => $stats,
            'user' => $user,
        ]);
    }
}
