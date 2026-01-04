<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use App\Services\ReadingPlanService;
use Carbon\Carbon;
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

        $viewData = [
            'plans' => $plansWithStatus,
        ];

        if ($request->header('HX-Request')) {
            return response()->htmx('plans.index', 'content', $viewData);
        }

        return view('plans.index', $viewData);
    }

    /**
     * Display a specific reading plan.
     * Redirects to today's reading if already subscribed.
     */
    public function show(Request $request, ReadingPlan $plan)
    {
        $user = $request->user();
        $subscription = $this->planService->getSubscription($user, $plan);

        // If already subscribed, redirect to today's reading
        if ($subscription) {
            if ($request->header('HX-Request')) {
                return response()
                    ->htmx('plans.today', 'content', $this->getTodayViewData($subscription))
                    ->header('HX-Push-Url', route('plans.today'));
            }

            return redirect()->route('plans.today');
        }

        $viewData = [
            'plan' => $plan,
        ];

        if ($request->header('HX-Request')) {
            return response()->htmx('plans.show', 'content', $viewData);
        }

        return view('plans.show', $viewData);
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
            return response()
                ->htmx('plans.today', 'content', $this->getTodayViewData($subscription))
                ->header('HX-Push-Url', route('plans.today'));
        }

        return redirect()->route('plans.today')
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
            return response()
                ->htmx('plans.index', 'content', ['plans' => $this->getPlansWithStatus($user)])
                ->header('HX-Push-Url', route('plans.index'));
        }

        return redirect()->route('plans.index')
            ->with('success', 'You have unsubscribed from this plan.');
    }

    /**
     * Display today's reading for the user's active plan.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeReadingPlan();

        if (! $subscription) {
            if ($request->header('HX-Request')) {
                return response()
                    ->htmx('plans.index', 'content', ['plans' => $this->getPlansWithStatus($user)])
                    ->header('HX-Push-Url', route('plans.index'));
            }

            return redirect()->route('plans.index')
                ->with('info', 'Subscribe to a reading plan to see your daily reading.');
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
    public function logChapter(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeReadingPlan();

        if (! $subscription) {
            return response()->json(['error' => 'No active subscription'], 400);
        }

        $maxDay = $subscription->plan->getDaysCount();
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

        // Return updated today view
        $viewData = $this->getTodayViewData($subscription, $dayNumber);

        return response()->htmx('plans.today', 'reading-list', $viewData);
    }

    /**
     * Log all chapters from today's reading.
     */
    public function logAll(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeReadingPlan();

        if (! $subscription) {
            return response()->json(['error' => 'No active subscription'], 400);
        }

        $maxDay = $subscription->plan->getDaysCount();
        $validated = $request->validate([
            'day' => 'required|integer|min:1|max:'.$maxDay,
        ]);
        $dayNumber = min(max($validated['day'], 1), $maxDay);
        $reading = $this->planService->getTodaysReadingWithStatus($subscription, $dayNumber);

        if (! $reading) {
            return response()->json(['error' => 'Invalid plan day'], 404);
        }

        if ($reading && $reading['all_completed']) {
            return response()->json(['error' => 'Plan day already complete'], 409);
        }

        if ($reading) {
            $chaptersToLog = array_filter(
                $reading['chapters'],
                fn ($ch) => ! $ch['completed']
            );

            $this->planService->logAllChapters($user, $subscription, $dayNumber, $chaptersToLog, Carbon::today());
        }

        // Return updated today view
        $viewData = $this->getTodayViewData($subscription, $dayNumber);

        return response()->htmx('plans.today', 'reading-list', $viewData);
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

        return [
            'subscription' => $subscription,
            'plan' => $subscription->plan,
            'reading' => $reading,
            'day_number' => $dayNumber,
            'current_day' => $currentDay,
            'total_days' => $totalDays,
            'progress' => $subscription->getProgress(),
            'is_complete' => $subscription->isComplete(),
        ];
    }

    /**
     * Get plans with subscription status for a user.
     */
    private function getPlansWithStatus($user)
    {
        $plans = ReadingPlan::active()->get();
        $subscriptions = $user->readingPlanSubscriptions()
            ->with('plan')
            ->get()
            ->keyBy('reading_plan_id');

        return $plans->map(function ($plan) use ($subscriptions) {
            return [
                'plan' => $plan,
                'subscription' => $subscriptions->get($plan->id),
                'is_subscribed' => $subscriptions->has($plan->id),
            ];
        });
    }
}
