<?php

namespace App\Http\Controllers;

use App\Services\AnnualRecapService;
use Illuminate\Http\Request;

class AnnualRecapController extends Controller
{
    public function __construct(
        private AnnualRecapService $recapService
    ) {}

    public function show(Request $request, ?int $year = null)
    {
        // Keep latest available recap first in the list.
        $availableYears = [2025];
        $latestYear = $availableYears[0];
        $viewMap = [
            2025 => 'annual-recap.2025.show',
        ];

        if ($year === null) {
            return redirect()->route('recap.show', ['year' => $latestYear]);
        }

        if (! in_array($year, $availableYears, true)) {
            return redirect()->route('recap.show', ['year' => $latestYear]);
        }

        $stats = $this->recapService->getRecap($request->user(), $year);

        // If no stats found (e.g. new user), show a "Not enough data" view or handle in the blade
        return view($viewMap[$year], [
            'stats' => $stats,
            'year' => $year,
            'user' => $request->user(),
            'theme' => 'cosmic',
        ]);
    }
}
