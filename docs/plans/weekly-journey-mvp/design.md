Weekly Journey — MVP Design

Overview
- New widget component with a neutral header and a right‑aligned status chip. Keeps the shared card shell consistent with other widgets but replaces the progress bar with a seven‑segment “journey bar”. No horizontal scroll; all seven segments visible at all sizes.

Placement & Replacement
- Full replacement of the current Weekly Goal widget on the dashboard.
- Replace usage in `resources/views/partials/dashboard-content.blade.php` where `<x-ui.weekly-goal-card>` is rendered with `<x-ui.weekly-journey-card>`.
- Leave the old component in code for now (no render paths) to ease rollback; remove in a later cleanup task if desired.

Component
- Name: WeeklyJourneyCard (Blade: `resources/views/components/ui/weekly-journey-card.blade.php`)
- Props
  - `currentProgress:int` — count of distinct reading days this week.
  - `days: array` — seven items for Sun→Sat: `{ date: 'YYYY-MM-DD', dow: 0..6 (Sun=0), isToday: bool, read: bool }`.
  - `weekRangeText:string` — e.g., `Oct 6–12` or `Dec 29–Jan 4` (no year shown).
  - `weeklyTarget:int` — static `7` for display context; used only for copy.
  - Optional `ctaEnabled:bool` — default true; CTA still gated by today’s state.

Layout
- Header (neutral background)
  - Left: Title `Weekly Journey`
  - Below title: subdued meta `weekRangeText`
  - Right: Status chip (text + optional icon), color‑coded by state
- Body
  - Big number line: `X days this week`
  - Journey bar: seven equal fixed‑width segments
    - Layout: CSS grid `grid grid-cols-7 gap-0.5 sm:gap-1`; segments `rounded-sm` with fixed height (`h-3 sm:h-4`)
    - Read: solid success color
    - Not yet: muted gray
    - Today: subtle primary ring/outline on the segment (always visible, whether read or not)
  - Day labels under bar: single letters `S M T W T F S` (today label slightly emphasized); hide on `xs` and show from `sm:` breakpoint up
- Footer row
  - Left: microcopy line matching status tone
  - Right: CTA button (conditional).

Behavior
- Status chip logic (copy + color)
  - Always visible, including at 0 days
  - 0: `Get started` — gray
  - 1–3: `Momentum` — info/teal
  - 4: `Keep going` — subdued success/green
  - 5–6: `Almost there` — bolder success/green
  - 7: `Perfect week` — gold with crown icon
- Microcopy (one line under bar; always visible)
  - 0: `Let’s start your week`
  - 1–3: `Nice start—keep going`
  - 4: `Solid week—keep reaching for 7`
  - 5–6: `So close to perfect`
  - 7: `Perfect week!` (elevated style: gold text treatment with small crown icon; no separate banner in MVP)
- CTA visibility
  - Show when `ctaEnabled === true` AND `today.read === false` AND `currentProgress < 7`
  - Button action: `hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"`
  - Label: `Log today’s reading`
- Tooltips
  - On hover, each segment shows a native tooltip via `title`, e.g., `Tue Oct 8 — read` or `Tue Oct 8 — not yet`
  - Touch devices: no tooltip; aria labels still provide full context
 - Interactivity
  - Day segments are non-interactive in MVP (no clicks/taps). Use `cursor-default` and avoid hover state changes beyond the tooltip.

Accessibility
- Each segment has `aria-label="Sun Oct 6 — read"` style strings and `title` for hover.
- Today’s segment uses `aria-current="date"`.
- Status chip includes `aria-live="polite"` text updates.

Visual System
- Colors
  - Segments: read = success green; not‑yet = gray; today outline = primary
  - Status chip: gray (0), teal/info (1–3), subdued success (4), bolder success (5–6), gold with crown (7)
  - CTA: existing brand accent (same class as navbar “Log Reading”)
- Icons
  - Crown icon only for perfect week (7/7); no checkmark at 4.
- Density
  - Compact spacing on small screens; same structure on desktop with wider gutters. No alternate mobile view.

Data & Source of Truth
- Week runs Sunday→Saturday (matches existing services).
- `days` array comes from server: extend `WeeklyGoalService` or add a helper to build the 7‑day map by querying distinct dates this week and marking `isToday`.
- `currentProgress` continues to use distinct day count.

States & Edge Cases
- New week (0 reads): all segments muted; chip shows `Get started`; CTA visible.
- Day 4: bar shows four filled; chip says `Solid week—keep going`; no special glyph.
- Day 7: all filled; chip goes gold with crown; CTA hidden.
- Regression (data edited): chip and bar recompute on refresh; no stale celebration.

Content Rules
- Title: fixed `Weekly Journey`.
- Week range: always without year (e.g., `Dec 29–Jan 4`), even if it spans years.
- Day labels: single letters below segments; hide labels at very narrow widths if needed, but keep aria labels.

Testing Notes
- Unit: service that builds `days` returns exactly seven items, correct `isToday`, and correct `read` flags for seeded logs.
- Feature: CTA visibility toggles correctly with/without a read logged today; perfect week hides CTA.
- View: status chip text and class mapping for progress thresholds (0, 1, 4, 6, 7).

Implementation Pointers
- Create `x-ui.weekly-journey-card` Blade with props above.
- Map colors using existing Tailwind tokens (success, gray, primary, accent), matching current design language.
- Reuse existing HTMX patterns from other widgets for partial refresh on `readingLogAdded`.
