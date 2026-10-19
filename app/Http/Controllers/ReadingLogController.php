<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingStep;
use App\Http\Requests\DeleteReadingChaptersRequest;
use App\Http\Requests\StoreReadingLogRequest;
use App\Http\Requests\UpdateReadingNotesRequest;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
use App\Services\OnboardingService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingFormService;
use App\Services\ReadingLogService;
use App\Services\UserStatisticsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ReadingLogController extends Controller
{
    public function __construct(
        private BibleReferenceService $bibleReferenceService,
        private ReadingLogService $readingLogService,
        private ReadingFormService $readingFormService,
        private UserStatisticsService $userStatisticsService,
        private OnboardingService $onboardingService,
        private AchievementService $achievementService,
        private ReadingCalendarService $readingCalendar
    ) {}

    /** Show the JavaScript reader form. */
    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();
        if ($this->onboardingService->shouldTrackPreFirstReading($user)) {
            $this->onboardingService->recordStep($user, OnboardingStep::LogFlowReached);
        }

        $today = $this->readingCalendar->todayFor($user);

        return Inertia::render('LogReading', [
            'config' => [
                'locale' => app()->getLocale(),
                'submitUrl' => route('logs.store'),
                'historyUrl' => route('logs.index'),
                'achievementsUrl' => route('achievements.index'),
                'achievementBadgeBaseUrl' => asset('images/achievements'),
                'csrfToken' => csrf_token(),
                'books' => $this->bibleReferenceService->listBibleBooks(null, 'en', $user->includesDeuterocanonicalBooks()),
                'recentBooks' => $this->readingFormService->getRecentBooksForForm($user),
                'dates' => ['today' => $today->toDateString(), 'yesterday' => $today->subDay()->toDateString()],
                'values' => [
                    'book_id' => '', 'start_chapter' => '', 'end_chapter' => '',
                    'date_read' => $today->toDateString(), 'notes_text' => '',
                ],
                'errors' => (object) [],
                'errorScopes' => (object) [],
                'successMessage' => '',
                'messages' => __('reading-log'),
            ],
        ]);
    }

    /**
     * Store a newly created reading log.
     */
    public function store(StoreReadingLogRequest $request)
    {
        try {
            $validated = $request->validated();
            $includeDeuterocanonical = $request->user()->includesDeuterocanonicalBooks();
            $start = (int) $validated['start_chapter'];
            $end = isset($validated['end_chapter']) ? (int) $validated['end_chapter'] : $start;

            // Format passage text using service
            $validated['passage_text'] = $this->bibleReferenceService->formatBibleReferenceRange(
                $validated['book_id'],
                $start,
                $end,
                includeDeuterocanonical: $includeDeuterocanonical
            );

            // Add chapter data for service
            if ($start !== $end) {
                $validated['chapters'] = range($start, $end);
            } else {
                $validated['chapter'] = $start;
            }

            $user = $request->user();

            // Create reading log using service
            $result = $this->readingLogService->logReadingWithResult($user, $validated);
            $log = $result->log;

            if ($request->wantsJson()) {
                $formContext = $this->readingFormService->getFormContextData($user);
                $celebration = $this->achievementService->getCelebrationPayload($user, $result->awardedAchievements, $log, $result->isFirstReadingOfDay);

                return response()->json([
                    'message' => "{$log->passage_text} recorded for {$log->date_read->format('M d, Y')}",
                    'reading' => [
                        'passage_text' => $log->passage_text,
                        'date_read' => $log->date_read->toDateString(),
                    ],
                    'form' => [
                        'books' => $this->bibleReferenceService->listBibleBooks(null, 'en', $includeDeuterocanonical),
                        'recent_books' => $formContext['recentBooks'],
                        'dates' => [
                            'today' => $this->readingCalendar->todayFor($user)->toDateString(),
                            'yesterday' => $this->readingCalendar->yesterdayFor($user)->toDateString(),
                        ],
                        'values' => [
                            'book_id' => '',
                            'start_chapter' => '',
                            'end_chapter' => '',
                            'date_read' => $this->readingCalendar->todayFor($user)->toDateString(),
                            'notes_text' => '',
                        ],
                    ],
                    'celebration' => $celebration,
                ]);
            }

            // Check if this is an HTMX request for the form replacement
            if ($request->header('HX-Request')) {
                $content = view('partials.reading-log-success-message', compact('log'))->render();

                $content .= $this->achievementCelebrationFragment($user, $result->awardedAchievements, $log, $result->isFirstReadingOfDay);

                return response($content)->header('HX-Trigger', 'readingLogAdded');
            } else {
                // For non-HTMX requests (tests, direct submissions), return the success message
                // This maintains backwards compatibility with existing tests
                return view('partials.reading-log-success-message', compact('log'));
            }
        } catch (ValidationException $e) {
            return $request->validationResponse($e->errors());
        } catch (InvalidArgumentException $e) {
            return $request->validationResponse(['chapter_range' => [$e->getMessage()]]);
        } catch (QueryException $e) {
            if (! in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw $e;
            }

            $yesterday = $this->readingCalendar->yesterdayFor($request->user())->toDateString();
            $dateLabel = $request->input('date_read') === $yesterday ? 'yesterday' : 'today';

            return $request->validationResponse([
                'chapter_range' => ["You have already logged one or more of these chapters for {$dateLabel}."],
            ]);
        }
    }

    private function achievementCelebrationFragment(User $user, Collection $awardedAchievements, ReadingLog $log, bool $isFirstReadingOfDay): string
    {
        $payload = $this->achievementService->getCelebrationPayload($user, $awardedAchievements, $log, $isFirstReadingOfDay);

        if (empty($payload['earned']) && empty($payload['record'])) {
            return '';
        }

        return view('components.celebrations.achievement-unlocks', [
            'payload' => $payload,
        ])->render();
    }

    /**
     * Display a listing of reading logs with infinite scroll pagination.
     * Supports both HTMX content loading and direct page access.
     */
    public function index(Request $request)
    {
        $logs = $this->readingLogService->getPaginatedDayGroupsFor($request, $this->userStatisticsService);

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
        }

        // Return full page for direct access or page container for HTMX navigation
        return response()->htmx('logs.index', 'page-content', compact('logs'));
    }

    /**
     * Update the notes associated with a reading log (or grouped logs).
     */
    public function updateNotes(UpdateReadingNotesRequest $request, ReadingLog $readingLog): Response
    {
        $user = $request->user();
        $data = $request->validated();
        $this->readingLogService->updateGroupNote(
            $user, $readingLog, $data['log_ids'], $data['notes_text']
        );

        $dates = collect([$readingLog->date_read->format('Y-m-d')]);

        $dayResponses = $this->readingLogService->getPreparedLogsForDates(
            $user,
            $dates->all(),
            $this->userStatisticsService
        );

        $orderedResponses = $dates
            ->mapWithKeys(fn ($date) => [$date => $dayResponses[$date] ?? null])
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
    public function destroyChapters(DeleteReadingChaptersRequest $request, ReadingLog $readingLog): Response
    {
        $user = $request->user();
        $data = $request->validated();
        $date = $readingLog->date_read->format('Y-m-d');
        $this->readingLogService->deleteGroupChapters($user, $readingLog, $data['group_ids'], $data['log_ids']);

        if ($request->header('HX-Request')) {
            $dayResponses = $this->readingLogService->getPreparedLogsForDates(
                $user,
                [$date],
                $this->userStatisticsService
            );

            $dayResponses = [$date => $dayResponses[$date] ?? null];

            return response()->view('partials.reading-log-update-response', [
                'primaryDate' => $date,
                'dayResponses' => $dayResponses,
                'userHasLogs' => $this->readingLogService->userHasAnyLogs($user),
            ])->header('HX-Trigger', json_encode([
                'hideModal' => ['id' => "delete-chapters-{$readingLog->id}"],
            ]));
        }

        return redirect()->route('logs.index')->with('success', 'Selected readings deleted successfully.');
    }
}
