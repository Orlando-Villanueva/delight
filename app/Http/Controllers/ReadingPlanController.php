<?php

namespace App\Http\Controllers;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Services\ReadingPlanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingPlanController extends Controller
{
    public function __construct(
        private ReadingPlanService $planService
    ) {}

    /**
     * Display a listing of available reading plans.
     */
    public function index(Request $request)
    {
        $plans = ReadingPlan::active()->get();
        $user = $request->user();

        // Get user's subscriptions for each plan
        $subscriptions = $user->readingPlanSubscriptions()
            ->with('plan')
            ->get()
            ->keyBy('reading_plan_id');

        $plansWithStatus = $plans->map(function ($plan) use ($subscriptions) {
            return [
                'plan' => $plan,
                'subscription' => $subscriptions->get($plan->id),
                'is_subscribed' => $subscriptions->has($plan->id),
            ];
        });

        $hasActivePlan = $subscriptions->contains(fn ($sub) => $sub->is_active);

        $viewData = [
            'plans' => $plansWithStatus,
            'has_active_plan' => $hasActivePlan,
        ];

        if ($request->header('HX-Request')) {
            return response()->htmx('plans.index', 'content', $viewData);
        }

        return view('plans.index', $viewData);
    }

    /**
     * Subscribe to a reading plan.
     */
    public function subscribe(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();
        $subscription = $this->planService->subscribe($user, $plan);

        if ($request->header('HX-Request')) {
            // Redirect to today's reading after subscribing
            $mainContent = view('plans.today', $this->getTodayViewData($subscription))->fragment('content');
            $oobContent = $this->getNavOobFragment($user);

            return response($mainContent.$oobContent)
                ->header('HX-Push-Url', route('plans.today', $plan));
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

        if ($request->header('HX-Request')) {
            $mainContent = view('plans.index', $this->getPlansWithStatus($user))->fragment('content');
            $oobContent = $this->getNavOobFragment($user);

            return response($mainContent.$oobContent)
                ->header('HX-Push-Url', route('plans.index'));
        }

        return redirect()->route('plans.index')
            ->with('success', 'You have unsubscribed from this plan.');
    }

    /**
     * Activate (resume) a reading plan subscription.
     */
    public function activate(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();
        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            if ($request->header('HX-Request')) {
                return response()
                    ->htmx('plans.index', 'content', $this->getPlansWithStatus($user))
                    ->header('HX-Push-Url', route('plans.index'));
            }

            return redirect()->route('plans.index')
                ->with('error', 'Subscription not found.');
        }

        $this->planService->activate($subscription);

        if ($request->header('HX-Request')) {
            $mainContent = view('plans.today', $this->getTodayViewData($subscription->fresh()))->fragment('reading-list');
            $oobContent = $this->getNavOobFragment($user);

            return response($mainContent.$oobContent)
                ->header('HX-Push-Url', route('plans.today', $plan));
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
        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            if ($request->header('HX-Request')) {
                return response()
                    ->htmx('plans.index', 'content', $this->getPlansWithStatus($user))
                    ->header('HX-Push-Url', route('plans.index'));
            }

            return redirect()->route('plans.index')
                ->with('info', 'Subscribe to this plan to see your daily reading.');
        }

        $totalDays = $subscription->plan->getDaysCount();
        $currentDay = $subscription->getDayNumber();
        $requestedDay = (int) $request->query('day', $currentDay);
        $viewDay = $totalDays > 0
            ? min(max($requestedDay, 1), $totalDays)
            : 0;

        $viewData = $this->getTodayViewData($subscription, $viewDay, $currentDay);

        if ($request->header('HX-Request')) {
            return response()->htmx('plans.today', 'content', $viewData);
        }

        return view('plans.today', $viewData);
    }

    /**
     * Log a single chapter from today's reading.
     */
    public function logChapter(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();
        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 400);
        }

        if (! $subscription->is_active) {
            return response()->json(['error' => 'Cannot log to inactive subscription'], 403);
        }

        $maxDay = $plan->getDaysCount();
        $validated = $request->validate([
            'book_id' => 'required|integer|min:1|max:66',
            'chapter' => 'required|integer|min:1',
            'day' => 'required|integer|min:1|max:'.$maxDay,
        ]);

        $dayNumber = min(max($validated['day'], 1), $maxDay);

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

        $this->planService->logChapter($user, $subscription, $dayNumber, $chapter, Carbon::today());
        $isFirstReading = $user->wasChanged('celebrated_first_reading_at');

        // Return updated today view
        $viewData = $this->getTodayViewData($subscription, $dayNumber);
        $viewData['isFirstReading'] = $isFirstReading;

        return response()->htmx('plans.today', 'reading-list', $viewData);
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

        $this->planService->logAllChapters($user, $subscription, $dayNumber, $chaptersToLog, Carbon::today());
        $isFirstReading = $user->wasChanged('celebrated_first_reading_at');

        // Return updated today view
        $viewData = $this->getTodayViewData($subscription, $dayNumber);
        $viewData['isFirstReading'] = $isFirstReading;

        return response()->htmx('plans.today', 'reading-list', $viewData);
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
        $unlinkedKeys = $this->getUnlinkedTodayChapterKeys($user->id, $subscription->id, $chapters);

        if (! empty($unlinkedKeys)) {
            $chaptersToApply = array_values(array_filter($chapters, function ($chapter) use ($unlinkedKeys) {
                return in_array($chapter['book_id'].'-'.$chapter['chapter'], $unlinkedKeys, true);
            }));

            if (! empty($chaptersToApply)) {
                $this->planService->logAllChapters(
                    $user,
                    $subscription,
                    $dayNumber,
                    $chaptersToApply,
                    Carbon::today()
                );
            }
        }

        $isFirstReading = $user->wasChanged('celebrated_first_reading_at');

        $viewData = $this->getTodayViewData($subscription, $dayNumber);
        $viewData['isFirstReading'] = $isFirstReading;

        return response()->htmx('plans.today', 'reading-list', $viewData);
    }

    /**
     * Validate and retrieve the reading for a given day from the request.
     *
     * @return array{user: \App\Models\User, subscription: \App\Models\ReadingPlanSubscription, dayNumber: int, reading: array}|JsonResponse
     */
    private function getValidatedDayReading(Request $request, ReadingPlan $plan): array|JsonResponse
    {
        $user = $request->user();
        $subscription = $user->readingPlanSubscriptions()
            ->where('reading_plan_id', $plan->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 400);
        }

        if (! $subscription->is_active) {
            return response()->json(['error' => 'Cannot log to inactive subscription'], 403);
        }

        $maxDay = $plan->getDaysCount();
        $validated = $request->validate([
            'day' => 'required|integer|min:1|max:'.$maxDay,
        ]);
        $dayNumber = min(max($validated['day'], 1), $maxDay);
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
        $totalDays = $subscription->plan->getDaysCount();
        $currentDay = $currentDay ?? $subscription->getDayNumber();
        $dayNumber = $dayNumber ?? $currentDay;
        $dayNumber = $totalDays > 0
            ? min(max($dayNumber, 1), $totalDays)
            : 0;
        $reading = $this->planService->getTodaysReadingWithStatus($subscription, $dayNumber);
        $unlinkedTodayChapterKeys = [];
        $unlinkedTodayTotal = 0;

        if ($reading) {
            $chapters = $reading['chapters'] ?? [];
            $unlinkedTodayTotal = count($chapters);
            $unlinkedTodayChapterKeys = $this->getUnlinkedTodayChapterKeys($subscription->user_id, $subscription->id, $chapters);
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
            'total_days' => $totalDays,
            'progress' => $subscription->getProgress(),
            'is_complete' => $subscription->isComplete(),
            'is_active' => $subscription->is_active,
            'has_other_active_plan' => $hasOtherActivePlan,
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
    private function getUnlinkedTodayChapterKeys(int $userId, int $subscriptionId, array $chapters): array
    {
        if (empty($chapters)) {
            return [];
        }

        $query = ReadingLog::where('user_id', $userId)
            ->whereDate('date_read', Carbon::today())
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
     * Get plans with subscription status for a user.
     *
     * @return array{plans: \Illuminate\Support\Collection, has_active_plan: bool}
     */
    private function getPlansWithStatus($user): array
    {
        $plans = ReadingPlan::active()->get();
        $subscriptions = $user->readingPlanSubscriptions()
            ->with('plan')
            ->get()
            ->keyBy('reading_plan_id');

        $plansWithStatus = $plans->map(function ($plan) use ($subscriptions) {
            return [
                'plan' => $plan,
                'subscription' => $subscriptions->get($plan->id),
                'is_subscribed' => $subscriptions->has($plan->id),
            ];
        });

        return [
            'plans' => $plansWithStatus,
            'has_active_plan' => $subscriptions->contains(fn ($sub) => $sub->is_active),
        ];
    }

    /**
     * Get the OOB fragment for updating navigation links.
     *
     * Computes the correct URL for the Reading Plans navigation link based on
     * the user's active subscription state, then renders the OOB partial.
     */
    private function getNavOobFragment($user): string
    {
        $smartPlansUrl = route('plans.index');

        $activeSubscription = $user->readingPlanSubscriptions()
            ->where('is_active', true)
            ->with('plan')
            ->first();

        if ($activeSubscription && $activeSubscription->plan) {
            $smartPlansUrl = route('plans.today', $activeSubscription->plan);
        }

        return view('partials.plans-nav-oob', ['smartPlansUrl' => $smartPlansUrl])->render();
    }
}
