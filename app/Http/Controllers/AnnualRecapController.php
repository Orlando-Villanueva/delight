<?php

namespace App\Http\Controllers;

use App\Services\AnnualRecapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class AnnualRecapController extends Controller
{
    public function __construct(
        private AnnualRecapService $recapService
    ) {}

    public function show(Request $request, ?int $year = null)
    {
        $availableYears = $this->getAvailableRecapYears();

        if (empty($availableYears)) {
            abort(404);
        }

        // Keep latest available recap first in the list.
        $latestYear = $availableYears[0];

        if ($year === null) {
            return redirect()->route('recap.show', ['year' => $latestYear]);
        }

        if (! in_array($year, $availableYears, true)) {
            return redirect()->route('recap.show', ['year' => $latestYear]);
        }

        $stats = $this->recapService->getRecap($request->user(), $year);
        $view = "annual-recap.{$year}.show";

        // If no stats found (e.g. new user), show a "Not enough data" view or handle in the blade
        return view($view, [
            'stats' => $stats,
            'year' => $year,
            'user' => $request->user(),
            'theme' => 'cosmic',
        ]);
    }

    private function getAvailableRecapYears(): array
    {
        $recapPath = resource_path('views/annual-recap');

        if (! File::isDirectory($recapPath)) {
            return [];
        }

        return collect(File::directories($recapPath))
            ->map(fn (string $directory) => basename($directory))
            ->filter(fn (string $directory) => ctype_digit($directory))
            ->map(fn (string $directory) => (int) $directory)
            ->filter(fn (int $year) => View::exists("annual-recap.{$year}.show"))
            ->sortDesc()
            ->values()
            ->all();
    }
}
