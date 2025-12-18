<?php

namespace App\Http\Controllers;

use App\Models\ReadingLog;
use App\Services\BibleReferenceService;
use App\Services\ReadingFormService;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
                'start_chapter' => 'required|integer|min:1',
                'end_chapter' => 'nullable|integer|min:1',
                'date_read' => "required|date|in:{$today},{$yesterday}",
                'notes_text' => 'nullable|string|max:1000',
            ]);

            $start = (int) $validated['start_chapter'];
            $end = isset($validated['end_chapter']) ? (int) $validated['end_chapter'] : $start;

            // Ensure start <= end (unless end was not provided, in which case it defaulted to start)
            if ($start > $end) {
                throw ValidationException::withMessages([
                    'start_chapter' => 'Invalid chapter range for the selected book.',
                ]);
            }

            // Validate chapter range using service
            if (! $this->bibleReferenceService->validateChapterRange(
                $validated['book_id'],
                $start,
                $end
            )) {
                throw ValidationException::withMessages([
                    'start_chapter' => 'Invalid chapter range for the selected book.',
                ]);
            }

            // Format passage text using service
            $validated['passage_text'] = $this->bibleReferenceService->formatBibleReferenceRange(
                $validated['book_id'],
                $start,
                $end
            );

            // Add chapter data for service
            if ($start !== $end) {
                $validated['chapters'] = range($start, $end);
            } else {
                $validated['chapter'] = $start;
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
                // Set success message for HTMX response (don't flash to session to avoid persistence issues)
                $successMessage = "{$log->passage_text} recorded for {$log->date_read->format('M d, Y')}";

                // Return just the form container with success message and reset form
                return response()
                    ->view('partials.reading-log-form', array_merge(
                        compact('books', 'errors', 'successMessage'),
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
            $errors = new MessageBag(['start_chapter' => [$e->getMessage()]]);

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
                $errors = new MessageBag(['start_chapter' => ['You have already logged one or more of these chapters for today.']]);

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
        $logs = $this->readingLogService->getPaginatedDayGroupsFor($request, $this->userStatisticsService);

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
     * Update the notes associated with a reading log (or grouped logs).
     */
    public function updateNotes(Request $request, ReadingLog $readingLog)
    {
        if ($request->user()->id !== $readingLog->user_id) {
            abort(403, 'Unauthorized to update this reading log.');
        }

        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'notes_text' => 'nullable|string|max:1000',
            'log_ids' => 'nullable|array',
            'log_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            $errors = new MessageBag($validator->errors()->toArray());
            $notesText = $request->input('notes_text', '');
            $allLogs = $this->readingLogService->getLogsForNoteUpdate(
                $user,
                $readingLog,
                $request->input('log_ids', [])
            );

            return response()
                ->view('components.modals.partials.edit-reading-note-form', [
                    'log' => $readingLog,
                    'modalId' => "edit-note-{$readingLog->id}",
                    'dateKey' => $readingLog->date_read->format('Y-m-d'),
                    'allLogs' => $allLogs,
                    'notesText' => $notesText,
                    'errors' => $errors,
                ], 422)
                ->header('HX-Retarget', "#edit-note-form-container-{$readingLog->id}")
                ->header('HX-Reswap', 'outerHTML');
        }

        $data = $validator->validated();
        $notesText = array_key_exists('notes_text', $data) ? (string) $data['notes_text'] : null;

        if ($notesText !== null) {
            $notesText = trim($notesText);

            if ($notesText === '') {
                $notesText = null;
            }
        }

        $logs = $this->readingLogService->getLogsForNoteUpdate(
            $user,
            $readingLog,
            $data['log_ids'] ?? []
        );

        if ($logs->isEmpty()) {
            abort(404, 'Reading logs not found.');
        }

        foreach ($logs as $log) {
            $this->readingLogService->updateReadingLog($log, [
                'notes_text' => $notesText,
            ]);
        }

        $dates = $logs
            ->map(fn($log) => $log->date_read->format('Y-m-d'))
            ->unique()
            ->values();

        $dayResponses = $this->readingLogService->getPreparedLogsForDates(
            $user,
            $dates->all(),
            $this->userStatisticsService
        );

        $orderedResponses = $dates
            ->mapWithKeys(fn($date) => [$date => $dayResponses[$date] ?? null])
            ->all();

        if ($request->header('HX-Request')) {
            $response = response()->view('partials.reading-log-update-response', [
                'primaryDate' => $readingLog->date_read->format('Y-m-d'),
                'dayResponses' => $orderedResponses,
                'userHasLogs' => $this->readingLogService->userHasAnyLogs($user),
            ]);

            $triggerPayload = [
                'hideModal' => ['id' => "edit-note-{$readingLog->id}"],
                'readingLogUpdated' => ['date' => $readingLog->date_read->format('Y-m-d')],
            ];

            return $response->header('HX-Trigger', json_encode($triggerPayload));
        }

        return redirect()
            ->route('logs.index')
            ->with('success', 'Reading note updated successfully.');
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

        $user = $request->user();
        $date = $readingLog->date_read->format('Y-m-d');

        // Delete the reading log (service handles book progress update)
        $this->readingLogService->deleteReadingLog($readingLog);

        // For HTMX requests, return targeted day + modal updates
        if ($request->header('HX-Request')) {
            $dayResponses = $this->readingLogService->getPreparedLogsForDates(
                $user,
                [$date],
                $this->userStatisticsService
            );

            // Ensure the response array contains the primary date key even when empty
            $dayResponses = [$date => $dayResponses[$date] ?? null];

            return view('partials.reading-log-update-response', [
                'primaryDate' => $date,
                'dayResponses' => $dayResponses,
                'userHasLogs' => $this->readingLogService->userHasAnyLogs($user),
            ]);
        }

        // For non-HTMX requests, redirect back
        return redirect()->route('logs.index')->with('success', 'Reading log deleted successfully.');
    }

    /**
     * Delete multiple reading logs in a single request.
     */
    public function batchDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique();

        if ($ids->isEmpty()) {
            return response()->json([
                'message' => 'Select at least one reading to delete.',
            ], 422);
        }

        $user = $request->user();

        $logs = ReadingLog::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get();

        $dates = $logs->map(fn($log) => $log->date_read->format('Y-m-d'))->unique()->values();

        foreach ($logs as $log) {
            $this->readingLogService->deleteReadingLog($log);
        }

        if ($request->header('HX-Request')) {
            $dayResponses = $this->readingLogService->getPreparedLogsForDates(
                $user,
                $dates->all(),
                $this->userStatisticsService
            );

            $orderedResponses = $dates
                ->mapWithKeys(fn($date) => [$date => $dayResponses[$date] ?? null])
                ->all();

            return view('partials.reading-log-update-response', [
                'primaryDate' => $dates->first(),
                'dayResponses' => $orderedResponses,
                'userHasLogs' => $this->readingLogService->userHasAnyLogs($user),
            ]);
        }

        return redirect()->route('logs.index')->with('success', 'Selected readings deleted successfully.');
    }
}
