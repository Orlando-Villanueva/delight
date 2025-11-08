# Weekly Journey MVP – Task Plan

## Commit 1 — `[DEL-000]` Build Weekly Journey data contract
1. Inventory `WeeklyGoalService` (and related cache helpers) to understand the existing `user_weekly_goal_*` payloads and identify the best extension point for the new Weekly Journey data contract (no mutations yet).
2. Add a dedicated `WeeklyJourneyService` (or extend the current service cleanly) that returns `currentProgress`, `days` (Sun→Sat with `date`, `dow`, `isToday`, `read`), `weekRangeText`, `weeklyTarget`, and a derived `ctaEnabled` flag so the Blade component receives a single cohesive view model.
3. Ensure the service respects Sunday–Saturday windows, reuses cached distinct-day counts, gracefully handles missing log data, and exposes helpers for “today’s” slot to simplify CTA gating.

## Commit 2 — `[DEL-000]` Implement `<x-ui.weekly-journey-card>`
4. Scaffold `resources/views/components/ui/weekly-journey-card.blade.php` with header (title + range), right-aligned status chip, body summary, journey bar grid, day labels, microcopy row, and CTA slot while reusing the shared card shell classes.
5. Encode the status chip and microcopy logic for progress thresholds (0, 1–3, 4, 5–6, 7) including tone-aware copy, stateful coloring, optional crown icon, and `aria-live` announcements; ensure milestone regressions automatically revert.
6. Render the seven segments as an HTMX-friendly, accessible grid: `grid-cols-7` equal widths, success/gray fills, today outline ring, `title` + `aria-label` text, `aria-current="date"` on today, and responsive day labels that hide on `xs` breakpoints.

## Commit 3 — `[DEL-000]` Swap dashboard widget and wire CTA
7. Replace `<x-ui.weekly-goal-card>` with `<x-ui.weekly-journey-card>` in `resources/views/partials/dashboard-content.blade.php`, passing the new service data while leaving the legacy component unused but intact for rollback.
8. Connect the CTA button to `route('logs.create')` with the prescribed `hx-*` attributes, show it only when `ctaEnabled` is true, today is unread, and the user has fewer than seven days logged, and confirm HTMX refresh behavior matches other dashboard widgets.
9. Double-check light/dark themes, spacing at mobile breakpoints, and loading/error fallbacks so the card respects existing dashboard layout constraints and does not shift when partials hot-swap.

## Commit 4 — `[DEL-000]` Add regression coverage
10. Add unit tests (Pest) for the new service to guarantee seven day objects, correct `isToday` flagging, accurate distinct-day counts, and week-range formatting for edge cases like year boundaries.
11. Create feature tests covering CTA visibility for (a) no readings, (b) today already logged, and (c) perfect week scenarios to lock in the encouragement logic.
12. Add view/component tests or snapshot assertions for status chip text/class mappings at each milestone threshold (0, 3, 4, 6, 7) to prevent regressions when tailoring copy or tokens later.
