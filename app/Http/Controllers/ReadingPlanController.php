<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingStep;
use App\Http\Requests\SubscribeReadingPlanRequest;
use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
use App\Services\OnboardingService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReadingPlanController extends Controller
{
    public function __construct(
        private ReadingPlanService $planService,
        private OnboardingService $onboardingService,
        private BibleReferenceService $bibleReferenceService,
        private AchievementService $achievementService,
        private ReadingCalendarService $readingCalendar
    ) {}

    /**
     * Display a listing of available reading plans.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        if ($this->onboardingService->shouldTrackPreFirstReading($user)) {
            $this->onboardingService->recordStep($user, OnboardingStep::PlanBrowserReached);
        }

        return Inertia::render('Plans', $this->catalogueProps($user));
    }

    /**
     * Subscribe to a reading plan.
     */
    public function subscribe(SubscribeReadingPlanRequest $request, ReadingPlan $plan): Response|RedirectResponse
    {
        $user = $request->user();
        $subscription = $this->planService->subscribe(
            $user,
            $plan,
            startDay: $request->integer('start_day')
        );

        if ($this->onboardingService->shouldTrackPreFirstReading($user)) {
            $this->onboardingService->recordStep($user, OnboardingStep::PlanSelected);
        }

        if ($request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('plans.today', $plan));
        }

        return redirect()->route('plans.today', $plan)
            ->with('success', "You've subscribed to {$plan->name}!");
    }

    /**
     * Unsubscribe from a reading plan.
     */
    public function unsubscribe(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();
        $this->planService->unsubscribe($user, $plan);

        return $this->redirectToCatalogue($request, 'success', "You have unsubscribed from {$plan->getShortName()}.");
    }

    /**
     * Activate (resume) a reading plan subscription.
     */
    public function activate(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();

        if (! $plan->isAvailableTo($user)) {
            return $this->unavailablePlanResponse($request);
        }

        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return $this->redirectToCatalogue($request, 'error', 'Subscription not found.');
        }

        $this->planService->activate($subscription);

        if ($request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('plans.today', $plan));
        }

        return redirect()->route('plans.today', $plan)
            ->with('success', "You've resumed {$plan->name}!");
    }

    /**
     * Display today's reading for a specific plan.
     */
    public function today(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();

        if (! $plan->isAvailableTo($user)) {
            return $this->unavailablePlanResponse($request);
        }

        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return $this->redirectToCatalogue($request, 'info', 'Subscribe to this plan to see your daily reading.');
        }

        $currentDay = $subscription->getDayNumber();
        $requestedDay = $request->query('day') !== null
            ? $request->integer('day')
            : $currentDay;
        $viewDay = $subscription->plan->getValidDayNumber($requestedDay, $currentDay);

        if ($request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('plans.today', ['plan' => $plan, 'day' => $viewDay]));
        }

        return Inertia::render('PlanToday', $this->mapTodayProps($subscription, $viewDay, $currentDay));
    }

    /**
     * Log a single chapter from today's reading.
     */
    public function logChapter(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();

        if (! $plan->isAvailableTo($user)) {
            return response()->json(['error' => 'This plan is not available for your selected canon'], 403);
        }

        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 400);
        }

        if (! $subscription->is_active) {
            return response()->json(['error' => 'Cannot log to inactive subscription'], 403);
        }

        $minDay = $plan->getFirstDayNumber();
        $maxDay = $plan->getLastDayNumber();
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $validated = $request->validate([
            'book_id' => [
                'required',
                'integer',
                Rule::in(collect($this->bibleReferenceService->listBibleBooks(includeDeuterocanonical: $includeDeuterocanonical))->pluck('id')->all()),
            ],
            'chapter' => 'required|integer|min:1',
            'day' => 'required|integer|min:'.$minDay.'|max:'.$maxDay,
        ]);

        $dayNumber = $validated['day'];

        if ($subscription->isBeforeTracking($dayNumber)) {
            return response()->json(['error' => 'Cannot log a plan day from before tracking began'], 403);
        }

        $chapter = [
            'book_id' => $validated['book_id'],
            'chapter' => $validated['chapter'],
        ];

        $reading = $this->planService->getTodaysReadingWithStatus($subscription, $dayNumber);

        if (! $reading) {
            return response()->json(['error' => 'Invalid plan day'], 404);
        }

        if ($reading && $reading['all_completed']) {
            return response()->json(['error' => 'Plan day already complete'], 409);
        }

        $result = $this->planService->logChapter($user, $subscription, $dayNumber, $chapter, $this->readingCalendar->todayFor($user)->toMutable());

        $celebration = $this->achievementService->getCelebrationPayload($user, $result->awardedAchievements, $result->log, $result->isFirstReadingOfDay);

        if (empty($celebration['earned']) && empty($celebration['record'])) {
            $celebration = null;
        }

        return $this->todayMutationResponse($request, $subscription, $dayNumber, $celebration);
    }

    /**
     * Log all chapters from today's reading.
     */
    public function logAll(Request $request, ReadingPlan $plan)
    {
        $result = $this->getValidatedDayReading($request, $plan);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        ['user' => $user, 'subscription' => $subscription, 'dayNumber' => $dayNumber, 'reading' => $reading] = $result;

        if ($reading['all_completed']) {
            return response()->json(['error' => 'Plan day already complete'], 409);
        }

        $chaptersToLog = array_filter(
            $reading['chapters'],
            fn ($ch) => ! $ch['completed']
        );

        $results = $this->planService->logAllChapters($user, $subscription, $dayNumber, $chaptersToLog, $this->readingCalendar->todayFor($user)->toMutable());

        return $this->todayMutationResponse($request, $subscription, $dayNumber, $this->celebrationPayloadForResults($user, $results));
    }

    /**
     * Apply today's existing logs to the current plan day.
     */
    public function applyTodaysReadings(Request $request, ReadingPlan $plan)
    {
        $result = $this->getValidatedDayReading($request, $plan);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        ['user' => $user, 'subscription' => $subscription, 'dayNumber' => $dayNumber, 'reading' => $reading] = $result;

        $chapters = $reading['chapters'] ?? [];
        $unlinkedKeys = $this->getUnlinkedTodayChapterKeys($user, $subscription->id, $chapters);

        if (! empty($unlinkedKeys)) {
            $chaptersToApply = array_values(array_filter($chapters, function ($chapter) use ($unlinkedKeys) {
                return in_array($chapter['book_id'].'-'.$chapter['chapter'], $unlinkedKeys, true);
            }));

            if (! empty($chaptersToApply)) {
                $results = $this->planService->logAllChapters(
                    $user,
                    $subscription,
                    $dayNumber,
                    $chaptersToApply,
                    $this->readingCalendar->todayFor($user)->toMutable()
                );
            }
        }

        return $this->todayMutationResponse($request, $subscription, $dayNumber, isset($results) ? $this->celebrationPayloadForResults($user, $results) : null);
    }

    /**
     * @param  array<string, mixed>|null  $celebration
     */
    private function todayMutationResponse(Request $request, ReadingPlanSubscription $subscription, int $dayNumber, ?array $celebration = null): JsonResponse|Response
    {
        if ($request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('plans.today', ['plan' => $subscription->plan, 'day' => $dayNumber]));
        }

        return response()->json([
            'today' => $this->mapTodayProps($subscription->fresh(), $dayNumber),
            'celebration' => $celebration,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTodayProps(ReadingPlanSubscription $subscription, int $dayNumber, ?int $currentDay = null): array
    {
        $data = $this->getTodayViewData($subscription, $dayNumber, $currentDay);
        $plan = $data['plan'];
        $reading = $data['reading'];

        return [
            'plan' => ['name' => $plan->getShortName(), 'slug' => $plan->slug],
            'subscription' => ['startDay' => $subscription->start_day],
            'reading' => $reading ? [
                'chapters' => collect($reading['chapters'])->map(fn (array $chapter): array => [
                    'bookId' => (int) $chapter['book_id'],
                    'bookName' => (string) $chapter['book_name'],
                    'chapter' => (int) $chapter['chapter'],
                    'completed' => (bool) $chapter['completed'],
                ])->values()->all(),
                'completedCount' => (int) $reading['completed_count'],
                'totalCount' => (int) $reading['total_count'],
                'allCompleted' => (bool) $reading['all_completed'],
            ] : null,
            'dayNumber' => (int) $data['day_number'],
            'currentDay' => (int) $data['current_day'],
            'totalDays' => (int) $data['total_days'],
            'previousDay' => $data['previous_day'],
            'nextDay' => $data['next_day'],
            'progress' => (float) $data['progress'],
            'isComplete' => (bool) $data['is_complete'],
            'isActive' => (bool) $data['is_active'],
            'isBeforeTracking' => (bool) $data['is_before_tracking'],
            'hasOtherActivePlan' => (bool) $data['has_other_active_plan'],
            'completedDaysCount' => (int) $data['completed_days_count'],
            'trackedDaysCount' => (int) $data['tracked_days_count'],
            'unlinkedTodayChaptersCount' => (int) $data['unlinked_today_chapters_count'],
            'unlinkedTodayChaptersTotal' => (int) $data['unlinked_today_chapters_total'],
            'urls' => [
                'today' => route('plans.today', $plan),
                'catalogue' => route('plans.index'),
                'activate' => route('plans.activate', $plan),
                'unsubscribe' => route('plans.unsubscribe', $plan),
                'logChapter' => route('plans.logChapter', $plan),
                'logAll' => route('plans.logAll', $plan),
                'applyReadings' => route('plans.applyTodaysReadings', $plan),
            ],
            'achievementsUrl' => route('achievements.index'),
            'achievementBadgeBaseUrl' => asset('images/achievements'),
            'flash' => [
                'success' => session()->pull('success'),
                'info' => session()->pull('info'),
                'error' => session()->pull('error'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $celebration
     * @return array<string, mixed>|null
     */
    private function celebrationPayloadForResults(User $user, Collection $results): ?array
    {
        if ($results->isEmpty()) {
            return null;
        }

        $awardedAchievements = $results->flatMap(fn ($result) => $result->awardedAchievements)->unique('id')->values();
        $lastResult = $results->last();
        $isFirstReadingOfDay = $results->contains(fn ($result): bool => $result->isFirstReadingOfDay);
        $payload = $this->achievementService->getCelebrationPayload($user, $awardedAchievements, $lastResult->log, $isFirstReadingOfDay);

        return empty($payload['earned']) && empty($payload['record']) ? null : $payload;
    }

    /**
     * Validate and retrieve the reading for a given day from the request.
     *
     * @return array{user: User, subscription: ReadingPlanSubscription, dayNumber: int, reading: array}|JsonResponse
     */
    private function getValidatedDayReading(Request $request, ReadingPlan $plan): array|JsonResponse
    {
        $user = $request->user();

        if (! $plan->isAvailableTo($user)) {
            return response()->json(['error' => 'This plan is not available for your selected canon'], 403);
        }

        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 400);
        }

        if (! $subscription->is_active) {
            return response()->json(['error' => 'Cannot log to inactive subscription'], 403);
        }

        $minDay = $plan->getFirstDayNumber();
        $maxDay = $plan->getLastDayNumber();
        $validated = $request->validate([
            'day' => 'required|integer|min:'.$minDay.'|max:'.$maxDay,
        ]);
        $dayNumber = $validated['day'];

        if ($subscription->isBeforeTracking($dayNumber)) {
            return response()->json(['error' => 'Cannot log a plan day from before tracking began'], 403);
        }

        $reading = $this->planService->getTodaysReadingWithStatus($subscription, $dayNumber);

        if (! $reading) {
            return response()->json(['error' => 'Invalid plan day'], 404);
        }

        return compact('user', 'subscription', 'dayNumber', 'reading');
    }

    /**
     * Get today's reading view data.
     */
    private function getTodayViewData($subscription, ?int $dayNumber = null, ?int $currentDay = null): array
    {
        $currentDay = $currentDay ?? $subscription->getDayNumber();
        $dayNumber = $dayNumber ?? $currentDay;
        $dayNumber = $subscription->plan->getValidDayNumber($dayNumber, $currentDay);
        $reading = $this->planService->getTodaysReadingWithStatus($subscription, $dayNumber);
        $isBeforeTracking = $subscription->isBeforeTracking($dayNumber);
        $unlinkedTodayChapterKeys = [];
        $unlinkedTodayTotal = 0;

        if ($reading && ! $isBeforeTracking) {
            $chapters = $reading['chapters'] ?? [];
            $unlinkedTodayTotal = count($chapters);
            $unlinkedTodayChapterKeys = $this->getUnlinkedTodayChapterKeys($subscription->user, $subscription->id, $chapters);
        }

        // Check if there's another active plan (not this one)
        $hasOtherActivePlan = ReadingPlanSubscription::where('user_id', $subscription->user_id)
            ->where('id', '!=', $subscription->id)
            ->where('is_active', true)
            ->exists();

        return [
            'subscription' => $subscription,
            'plan' => $subscription->plan,
            'reading' => $reading,
            'day_number' => $dayNumber,
            'current_day' => $currentDay,
            'total_days' => $subscription->plan->getLastDayNumber(),
            'previous_day' => $subscription->plan->getPreviousDayNumber($dayNumber),
            'next_day' => $subscription->plan->getNextDayNumber($dayNumber),
            'progress' => $subscription->getProgress(),
            'is_complete' => $subscription->isComplete(),
            'is_active' => $subscription->is_active,
            'is_before_tracking' => $isBeforeTracking,
            'has_other_active_plan' => $hasOtherActivePlan,
            'tracked_days_count' => $subscription->getTrackedDaysCount(),
            'completed_days_count' => $subscription->getCompletedDaysCount(),
            'unlinked_today_chapters_count' => count($unlinkedTodayChapterKeys),
            'unlinked_today_chapters_total' => $unlinkedTodayTotal,
        ];
    }

    /**
     * Get today's unlinked chapter keys for the provided plan chapters.
     *
     * Finds reading logs from today that match the given chapters but are NOT
     * yet linked to the specified subscription (they may be linked to other
     * subscriptions or completely unlinked).
     *
     * @return array<int, string>
     */
    private function getUnlinkedTodayChapterKeys(User $user, int $subscriptionId, array $chapters): array
    {
        if (empty($chapters)) {
            return [];
        }

        $query = ReadingLog::where('user_id', $user->id)
            ->whereDate('date_read', $this->readingCalendar->todayFor($user)->toMutable())
            ->whereDoesntHave('planCompletions', function ($query) use ($subscriptionId) {
                $query->where('reading_plan_subscription_id', $subscriptionId);
            })
            ->where(function ($query) use ($chapters) {
                foreach ($chapters as $chapter) {
                    $query->orWhere(function ($query) use ($chapter) {
                        $query->where('book_id', $chapter['book_id'])
                            ->where('chapter', $chapter['chapter']);
                    });
                }
            })
            ->select(['book_id', 'chapter'])
            ->distinct()
            ->get();

        return $query->map(fn ($log) => $log->book_id.'-'.$log->chapter)->unique()->values()->toArray();
    }

    /**
     * @return array{
     *     plans: list<array<string, mixed>>,
     *     hasActivePlan: bool,
     *     flash: array{info: ?string, error: ?string, success: ?string}
     * }
     */
    private function catalogueProps(User $user): array
    {
        $plans = ReadingPlan::active()->availableTo($user)->get();
        $subscriptions = $user->readingPlanSubscriptions()
            ->whereIn('reading_plan_id', $plans->pluck('id'))
            ->with(['plan', 'dayCompletions.readingLog:id,book_id,chapter'])
            ->get()
            ->keyBy('reading_plan_id');

        return [
            'plans' => $plans->map(function (ReadingPlan $plan) use ($subscriptions): array {
                $subscription = $subscriptions->get($plan->id);
                $canStart = $subscription === null && $plan->getDaysCount() > 0;

                return [
                    'slug' => $plan->slug,
                    'name' => $plan->getShortName(),
                    'description' => $plan->description,
                    'daysCount' => $plan->getDaysCount(),
                    'canStart' => $canStart,
                    'firstDay' => $plan->getFirstDayNumber(),
                    'subscribeUrl' => $canStart ? route('plans.subscribe', $plan) : null,
                    'days' => $canStart
                        ? collect($plan->days ?? [])->map(function (array $day): array {
                            $dayNumber = (int) $day['day'];

                            return [
                                'day' => $dayNumber,
                                'optionLabel' => $day['label'].' - Day '.$dayNumber,
                            ];
                        })->values()->all()
                        : [],
                    'subscription' => $subscription instanceof ReadingPlanSubscription ? [
                        'active' => (bool) $subscription->is_active,
                        'dayNumber' => $subscription->getDayNumber(),
                        'lastDay' => $plan->getLastDayNumber(),
                        'completedDays' => $subscription->getCompletedDaysCount(),
                        'trackedDays' => $subscription->getTrackedDaysCount(),
                        'progress' => $subscription->getProgress(),
                        'todayUrl' => route('plans.today', $plan),
                    ] : null,
                ];
            })->values()->all(),
            'hasActivePlan' => $subscriptions->contains(fn (ReadingPlanSubscription $subscription): bool => (bool) $subscription->is_active),
            'flash' => [
                'info' => session('info'),
                'error' => session('error'),
                'success' => session('success'),
            ],
        ];
    }

    private function redirectToCatalogue(Request $request, ?string $flashKey = null, ?string $flashMessage = null): RedirectResponse|Response
    {
        if ($flashKey !== null && $flashMessage !== null) {
            $request->session()->flash($flashKey, $flashMessage);
        }

        if ($request->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('plans.index'));
        }

        return redirect()->route('plans.index');
    }

    private function unavailablePlanResponse(Request $request): Response|RedirectResponse
    {
        return $this->redirectToCatalogue($request, 'info', 'Enable deuterocanonical books in Settings to use this plan.');
    }
}
