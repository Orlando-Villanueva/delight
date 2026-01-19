# DEL-219: Onboarding Flow Implementation Plan

## Overview

**Goal:** Reduce "never read" percentage from 49% to under 25% by guiding new users to log their first reading within the first session.

**Approach:** Modal overlay on dashboard for new users who haven't logged a reading yet.

**Priority:** Ship fast. The core onboarding modal is MVP; other items can ship separately.

---

## Architecture Decisions

### Why Modal over Full-Page?
- Faster to implement (reuse existing modal patterns)
- Less disruptive to existing flows
- User still sees the dashboard they'll use daily
- Can be dismissed and re-triggered if needed

### User State Detection
A user needs onboarding if:
1. They have zero reading logs (`readingLogs()->count() === 0`)
2. They haven't dismissed the onboarding (`onboarding_dismissed_at` is null)

We'll add a flag to track dismissal so returning users who intentionally skipped aren't nagged.

---

## Implementation Phases

### Phase 1: Core Onboarding Modal (MVP)
**Estimated time: 2-3 hours** — Ship this first

#### 1.1 Database Migration
Add `onboarding_dismissed_at` timestamp to users table.

```php
// database/migrations/xxxx_add_onboarding_dismissed_at_to_users.php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('onboarding_dismissed_at')->nullable();
});
```

#### 1.2 User Model Updates
```php
// app/Models/User.php
protected $fillable = [
    // ... existing
    'onboarding_dismissed_at',
];

protected function casts(): array
{
    return [
        // ... existing
        'onboarding_dismissed_at' => 'datetime',
    ];
}

public function needsOnboarding(): bool
{
    return $this->readingLogs()->count() === 0 
        && $this->onboarding_dismissed_at === null;
}
```

#### 1.3 Dashboard Controller Updates
```php
// app/Http/Controllers/DashboardController.php
public function index(Request $request)
{
    $user = auth()->user();
    // ... existing code ...
    
    $showOnboarding = $user->needsOnboarding();
    
    return response()->htmx('dashboard', 'dashboard-content', compact(
        // ... existing vars ...
        'showOnboarding'
    ));
}
```

#### 1.4 Onboarding Modal Component

**File:** `resources/views/components/modals/onboarding-welcome.blade.php`

Modal includes:
- Header: "Welcome to Delight!" with subtitle and dismiss (X) button
- Visual: Book icon in accent-colored circle
- Body: "How it works" with 3 numbered steps
- Primary CTA: "Log Your First Reading" button
- Secondary: "or start with a reading plan" link

#### 1.5 Dismiss Onboarding Route & Controller
```php
// routes/web.php
Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])
    ->name('onboarding.dismiss')
    ->middleware('auth');

// app/Http/Controllers/OnboardingController.php
public function dismiss(Request $request)
{
    $request->user()->update([
        'onboarding_dismissed_at' => now(),
    ]);

    return response()->noContent();
}
```

#### 1.6 Include Modal in Dashboard
Modal auto-opens on page load for eligible users using Flowbite's modal API.

#### 1.7 Files to Create/Modify

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/xxxx_add_onboarding_dismissed_at_to_users.php` | Create | Add timestamp column |
| `app/Models/User.php` | Modify | Add fillable, cast, and `needsOnboarding()` |
| `app/Http/Controllers/DashboardController.php` | Modify | Pass `$showOnboarding` to view |
| `app/Http/Controllers/OnboardingController.php` | Create | Handle dismiss action |
| `routes/web.php` | Modify | Add dismiss route |
| `resources/views/components/modals/onboarding-welcome.blade.php` | Create | Modal component |
| `resources/views/dashboard.blade.php` | Modify | Include modal conditionally |

---

### Phase 2: First Reading Celebration (Ship Separately)
**Estimated time: 1-2 hours**

After user logs their first reading, show a special celebration message instead of the standard success message.

#### 2.1 Detection Logic
```php
// app/Http/Controllers/ReadingLogController.php
public function store(StoreReadingLogRequest $request)
{
    $user = $request->user();
    $isFirstReading = $user->readingLogs()->count() === 0;
    
    // ... create the reading log ...
    
    if ($isFirstReading) {
        return $this->firstReadingSuccess($log);
    }
    
    return $this->standardSuccess($log);
}
```

#### 2.2 First Reading Success View
Celebration view includes:
- Party emoji icon
- "You did it!" heading
- Stats preview (1 day streak, chapters logged)
- CTAs: "View Dashboard" / "Log Another"

#### 2.3 Files to Create/Modify

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/ReadingLogController.php` | Modify | Detect first reading |
| `resources/views/partials/first-reading-success.blade.php` | Create | Celebration view |

---

### Phase 3: 24-Hour Follow-Up Email (Deferred)
**Estimated time: 2-3 hours** — Ship separately after Phase 1 is validated

Send a gentle nudge email to users who registered but haven't logged a reading within 24 hours.

#### 3.1 Scheduled Command
```php
// app/Console/Commands/SendOnboardingReminders.php
// Find users registered 24-25h ago with no readings, send reminder email
```

#### 3.2 Schedule the Command
```php
// routes/console.php
Schedule::command('onboarding:send-reminders')->hourly();
```

#### 3.3 Migration
```php
$table->timestamp('onboarding_reminder_sent_at')->nullable();
```

#### 3.4 Files to Create/Modify

| File | Action |
|------|--------|
| `database/migrations/xxxx_add_onboarding_reminder_sent_at_to_users.php` | Create |
| `app/Console/Commands/SendOnboardingReminders.php` | Create |
| `app/Notifications/OnboardingReminderNotification.php` | Create |
| `resources/views/emails/onboarding-reminder.blade.php` | Create |
| `routes/console.php` | Modify |

---

## UI/UX Design Specifications

### Modal Design Tokens
Following existing patterns in `app.css` and modal components:

| Element | Light Mode | Dark Mode |
|---------|------------|-----------|
| Background | `bg-white` | `dark:bg-gray-800` |
| Border | `border-gray-200` | `dark:border-gray-700` |
| Title | `text-gray-900` | `dark:text-white` |
| Body text | `text-gray-600` | `dark:text-gray-300` |
| Secondary text | `text-gray-500` | `dark:text-gray-400` |
| Primary CTA | `bg-accent-500` | `dark:bg-accent-600` |
| Secondary action | `text-primary-600` | `dark:text-primary-400` |

### Modal Layout
```
┌─────────────────────────────────────┐
│ Welcome to Delight!            [X]  │
│ Let's get you started...            │
├─────────────────────────────────────┤
│         ┌────────┐                  │
│         │  📖    │                  │
│         └────────┘                  │
│   Building a consistent Bible       │
│   reading habit starts with one     │
│   chapter.                          │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ How it works:                   │ │
│ │ ① Read a chapter               │ │
│ │ ② Log what you read            │ │
│ │ ③ Watch your progress grow     │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│  ┌─────────────────────────────────┐│
│  │   Log Your First Reading →     ││
│  └─────────────────────────────────┘│
│     or start with a reading plan    │
└─────────────────────────────────────┘
```

### Modal Behavior
- **Trigger:** Auto-open on dashboard load for eligible users
- **Backdrop:** Static (click outside doesn't close) — forces intentional action
- **Close actions:**
  - X button → calls dismiss endpoint, closes modal
  - "Log Your First Reading" → navigates to `/logs/create`
  - "start with a reading plan" → navigates to `/plans`
- **Keyboard:** ESC closes and dismisses

### Responsive Design
- **Mobile (< 640px):** Full-width modal with comfortable padding
- **Tablet/Desktop:** Centered modal, max-width 512px (`max-w-lg`)

### Accessibility
- `aria-modal="true"` on modal container
- Focus trap within modal
- Semantic heading structure (h3 for title)
- Button labels are descriptive
- `sr-only` text for icon-only close button

---

## Testing Plan

### Unit Tests
```php
// tests/Unit/UserTest.php
it('needs onboarding when user has no reading logs', function () {
    $user = User::factory()->create();
    expect($user->needsOnboarding())->toBeTrue();
});

it('does not need onboarding after logging a reading', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create();
    expect($user->needsOnboarding())->toBeFalse();
});

it('does not need onboarding after dismissing', function () {
    $user = User::factory()->create(['onboarding_dismissed_at' => now()]);
    expect($user->needsOnboarding())->toBeFalse();
});
```

### Feature Tests
```php
// tests/Feature/OnboardingTest.php
it('shows onboarding modal for new users on dashboard', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSee('onboarding-modal')
        ->assertSee('Welcome to Delight!');
});

it('does not show onboarding for users with readings', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create();
    
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertDontSee('onboarding-modal');
});

it('dismisses onboarding and sets timestamp', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/onboarding/dismiss')
        ->assertNoContent();
    
    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
});
```

---

## Rollout Plan

### Phase 1 Launch Checklist
- [ ] Run migration on production
- [ ] Deploy code changes
- [ ] Verify modal appears for test accounts
- [ ] Verify dismiss works correctly
- [ ] Verify navigation to `/logs/create` works
- [ ] Monitor for errors in Telescope

### Success Metrics
Track for 2-4 weeks after launch:
1. **First reading conversion rate:** % of new signups who log within 24h
2. **Onboarding interaction:** Modal views vs. CTA clicks vs. dismissals
3. **"Never read" cohort:** Target < 25% (currently 49%)

---

## Summary: What Ships When

| Phase | Scope | Time | Ship |
|-------|-------|------|------|
| **Phase 1** | Onboarding modal on dashboard | 2-3h | MVP - ship first |
| **Phase 2** | First reading celebration | 1-2h | Nice-to-have, ship separately |
| **Phase 3** | 24h follow-up email | 2-3h | Deferred, validate Phase 1 first |

---

## Open Questions

1. **Copy review:** Should we A/B test different modal copy?
2. **Analytics:** Do we want to add event tracking before shipping?
3. **Reading plan prominence:** Is the "or start with a reading plan" link enough, or should it be more visible?

---

*Created: 2026-01-17*
*Linear Issue: DEL-219*
