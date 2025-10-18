<?php

namespace App\Http\Controllers;

use App\Models\ReadingLog;
use App\Services\BibleReferenceService;
use App\Services\ReadingFormService;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReadingLogController extends Controller
{
    public function __construct(
        private BibleReferenceService $bibleReferenceService,
        private ReadingLogService $readingLogService,
        private ReadingFormService $readingFormService,
        private UserStatisticsService $userStatisticsService
    ) {}

    /**
     * Show the form for creating a new reading log.
     * Returns either the page view or HTMX content partial based on request type.
     */
    public function create(Request $request)
    {
        // TEMPORARY: Test French support by loading French book names
        // You can change 'fr' to 'en' to switch back to English
        $locale = $request->get('lang', 'en'); // Allow testing via ?lang=fr
        $books = $this->bibleReferenceService->listBibleBooks(null, $locale);

        // Pass empty error bag for consistent template behavior
        $errors = new MessageBag;

        // Get form context data (yesterday logic, streak info)
        $formContext = $this->readingFormService->getFormContextData($request->user());

        $data = array_merge(compact('books', 'errors'), $formContext);

        // Return appropriate view based on request type
        if ($request->header('HX-Request')) {
            // For HTMX requests, return the page container partial
            return view('partials.reading-log-create-page', $data);
        }

        // For direct page access, return the full page template
        return view('logs.create', $data);
    }

    /**
     * Store a newly created reading log.
     */
    public function store(Request $request)
    {
        try {
            // Late Logging Grace: Only allow today or yesterday
            $today = today()->toDateString();
            $yesterday = today()->subDay()->toDateString();

            $validated = $request->validate([
                'book_id' => 'required|integer|min:1|max:66',
                'chapter_input' => ['required', 'string', 'regex:/^(\d+|\d+-\d+)$/'],
                'date_read' => "required|date|in:{$today},{$yesterday}",
                'notes_text' => 'nullable|string|max:1000',
            ]);

            // Parse chapter input (single or range)
            $chapterData = $this->bibleReferenceService->parseChapterInput($validated['chapter_input']);

            // Validate chapter range using service
            if (! $this->bibleReferenceService->validateChapterRange(
                $validated['book_id'],
                $chapterData['start'],
                $chapterData['end']
            )) {
                throw ValidationException::withMessages([
                    'chapter_input' => 'Invalid chapter range for the selected book.',
                ]);
            }

            // Format passage text using service
            $validated['passage_text'] = $this->bibleReferenceService->formatBibleReferenceRange(
                $validated['book_id'],
                $chapterData['start'],
                $chapterData['end']
            );

            // Add chapter data for service
            if ($chapterData['type'] === 'range') {
                $validated['chapters'] = $chapterData['chapters'];
            } else {
                $validated['chapter'] = $chapterData['start'];
            }

            // Create reading log using service
            $log = $this->readingLogService->logReading($request->user(), $validated);

            // Check if this is an HTMX request for the form replacement
            if ($request->header('HX-Request')) {
                // Get fresh form data for page display
                $books = $this->bibleReferenceService->listBibleBooks(null, 'en');
                $errors = new MessageBag;
                $formContext = $this->readingFormService->getFormContextData($request->user());

                // Set success message
                session()->flash('success', "{$log->passage_text} recorded for {$log->date_read->format('M d, Y')}");

                // Return just the form container with success message and reset form
                return response()
                    ->view('partials.reading-log-form', array_merge(
                        compact('books', 'errors'),
                        $formContext
                    ))
                    ->header('HX-Trigger', 'readingLogAdded');
            } else {
                // For non-HTMX requests (tests, direct submissions), return the success message
                // This maintains backwards compatibility with existing tests
                return view('partials.reading-log-success-message', compact('log'));
            }
        } catch (ValidationException $e) {
            // Get books data for form re-display
            $books = $this->bibleReferenceService->listBibleBooks(null, 'en');

            // Pass errors directly to the view
            $errors = new MessageBag($e->errors());

            // Get form context data (yesterday logic, streak info)
            $formContext = $this->readingFormService->getFormContextData($request->user());

            // Return appropriate partial based on request type
            $partial = $request->header('HX-Request') ? 'partials.reading-log-form' : 'logs.create';

            return view($partial, array_merge(
                compact('books', 'errors'),
                $formContext
            ));
        } catch (InvalidArgumentException $e) {
            // Get books data for form re-display
            $books = $this->bibleReferenceService->listBibleBooks(null, 'en');

            // Create error bag for form display
            $errors = new MessageBag(['chapter_input' => [$e->getMessage()]]);

            // Get form context data (yesterday logic, streak info)
            $formContext = $this->readingFormService->getFormContextData($request->user());

            // Return appropriate partial based on request type
            $partial = $request->header('HX-Request') ? 'partials.reading-log-form' : 'logs.create';

            return view($partial, array_merge(
                compact('books', 'errors'),
                $formContext
            ));
        } catch (QueryException $e) {
            // Handle unique constraint violation (duplicate reading log)
            if ($e->getCode() === '23000') {
                // Get books data for form re-display
                $books = $this->bibleReferenceService->listBibleBooks(null, 'en');

                // Create error bag for form display
                $errors = new MessageBag(['chapter_input' => ['You have already logged one or more of these chapters for today.']]);

                // Get form context data (yesterday logic, streak info)
                $formContext = $this->readingFormService->getFormContextData($request->user());

                // Return appropriate partial based on request type
                $partial = $request->header('HX-Request') ? 'partials.reading-log-create-page' : 'logs.create';

                return view($partial, array_merge(
                    compact('books', 'errors'),
                    $formContext
                ));
            }

            // Re-throw if it's a different database error
            throw $e;
        }
    }

    /**
     * Display a listing of reading logs with infinite scroll pagination.
     * Supports both HTMX content loading and direct page access.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get reading logs - all records, chronologically ordered
        $allLogs = $user->readingLogs()->recentFirst();

        // Group by date_read to show all readings for each day
        $groupedLogs = $allLogs->get()
            ->groupBy(function ($log) {
                return $log->date_read->format('Y-m-d'); // Group by date only
            })
            ->map(function ($logsForDay) {
                // Deduplicate readings within each day by passage + date + created_at (same session)
                $deduplicated = $logsForDay->groupBy(function ($log) {
                    return $log->passage_text.'|'.$log->date_read.'|'.$log->created_at->format('Y-m-d H:i:s');
                })
                    ->map(function ($group) {
                        // Keep all logs in the group for deletion purposes
                        $displayLog = $group->first(); // Take the first entry for display
                        $displayLog->all_logs = $group; // Attach all logs for deletion modal

                        return $displayLog;
                    })
                    ->values();

                // Add time_ago to each log and sort readings within each day by created_at (newest first)
                return $deduplicated->map(function ($log) {
                    // Use the service's smart time calculation for consistent display across all components
                    $log->time_ago = $this->userStatisticsService->calculateSmartTimeAgo($log);
                    $log->logged_time_ago = $this->userStatisticsService->formatTimeAgo($log->created_at);

                    return $log;
                })->sortByDesc('created_at')->values();
            })
            ->sortByDesc(function ($logsForDay, $date) {
                return $date; // Sort days by date (newest first)
            });

        // Manual pagination by days (not individual logs)
        $perPage = 8; // Number of days to show per page
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        // Paginate the day groups
        $paginatedDays = $groupedLogs->slice($offset, $perPage);
        $logs = new LengthAwarePaginator(
            $paginatedDays,
            $groupedLogs->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'pageName' => 'page']
        );
        $logs->withQueryString();

        // Return appropriate view based on request type
        if ($request->header('HX-Request')) {
            // If it's an infinite scroll request (has page parameter), return just the new cards
            if ($request->has('page') && $request->get('page') > 1) {
                $cardsHtml = $this->readingLogService->renderReadingLogCardsHtml($logs);

                return response($cardsHtml);
            }

            // If this is a refresh request (from readingLogAdded trigger), return just the list
            if ($request->has('refresh')) {
                return view('partials.reading-log-list', compact('logs'));
            }

            // Otherwise, return the page container for HTMX navigation
            return view('partials.logs-page', compact('logs'));
        }

        // Return full page for direct access (browser URL)
        return view('logs.index', compact('logs'));
    }

    /**
     * Delete a reading log entry.
     */
    public function destroy(Request $request, ReadingLog $readingLog)
    {
        // Authorize the deletion
        if ($request->user()->id !== $readingLog->user_id) {
            abort(403, 'Unauthorized to delete this reading log.');
        }

        // Store the date for re-fetching logs after deletion
        $dateRead = $readingLog->date_read->format('Y-m-d');

        // Delete the reading log (service handles book progress update)
        $this->readingLogService->deleteReadingLog($readingLog);

        // For HTMX requests, refresh the entire log list
        if ($request->header('HX-Request')) {
            $user = $request->user();

            // Get all logs grouped by date (same logic as index)
            $allLogs = $user->readingLogs()->recentFirst();

            $groupedLogs = $allLogs->get()
                ->groupBy(function ($log) {
                    return $log->date_read->format('Y-m-d');
                })
                ->map(function ($logsForDay) {
                    $deduplicated = $logsForDay->groupBy(function ($log) {
                        return $log->passage_text.'|'.$log->date_read.'|'.$log->created_at->format('Y-m-d H:i:s');
                    })
                        ->map(function ($group) {
                            // Keep all logs in the group for deletion purposes
                            $displayLog = $group->first();
                            $displayLog->all_logs = $group; // Attach all logs for deletion modal

                            return $displayLog;
                        })
                        ->values();

                    return $deduplicated->map(function ($log) {
                        $log->time_ago = $this->userStatisticsService->calculateSmartTimeAgo($log);
                        $log->logged_time_ago = $this->userStatisticsService->formatTimeAgo($log->created_at);

                        return $log;
                    })->sortByDesc('created_at')->values();
                })
                ->sortByDesc(function ($logsForDay, $date) {
                    return $date;
                });

            // Manual pagination
            $perPage = 8;
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;

            $paginatedDays = $groupedLogs->slice($offset, $perPage);
            $logs = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedDays,
                $groupedLogs->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'pageName' => 'page']
            );
            $logs->withQueryString();

            // Return the entire log list with success message
            return response()
                ->view('partials.reading-log-list', compact('logs'))
                ->header('HX-Trigger', 'readingLogDeleted');
        }

        // For non-HTMX requests, redirect back
        return redirect()->route('logs.index')->with('success', 'Reading log deleted successfully.');
    }
}
