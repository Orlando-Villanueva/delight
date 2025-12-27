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
        // Default to the current year if not specified
        // If it's early January (e.g. up to Jan 31st), and no year is specified,
        // we might defaults to previous year, but for simplicity let's default to 2025 as requested
        // or just the current year from system time.
        // Given the prompt context "It's already December 26th", defaults to current year is correct.

        $year ??= now()->year;

        // Prevent peeking into future years or way past years
        if ($year > now()->year) {
            return redirect()->route('recap.show', ['year' => now()->year]);
        }

        $stats = $this->recapService->getRecap($request->user(), $year);

        // If no stats found (e.g. new user), show a "Not enough data" view or handle in the blade
        return view('annual-recap.show', [
            'stats' => $stats,
            'year' => $year,
            'user' => $request->user(),
            'theme' => 'cosmic',
        ]);
    }
}
