# Weekly Journey Requirements

These stories capture the minimum lovable version of the Weekly Journey refresh. Each item should deliver a noticeable win for readers while staying feasible with today’s data model.

## Story 1 – Weekly Journey Overview
- **As** a reader landing on the dashboard
- **I want** a “Weekly Journey” card that summarizes my momentum for the current Sunday–Saturday window
- **So that** the dashboard immediately tells me how my week is going beyond a binary goal
- **Acceptance**
  - Card replaces the existing Weekly Goal widget in the same layout slot
  - Header surfaces week range, overall status headline, and contextual microcopy
  - Summary reflects up-to-date data from the current cache window (`user_weekly_goal_*`)

## Story 2 – Daily Participation Stripe
- **As** a reader scanning the card
- **I want** seven day tiles that make it obvious which days I’ve already shown up this week
- **So that** I can quickly spot wins, misses, and opportunities
- **Acceptance**
  - Tiles render in chronological order and show today’s column distinctly
  - Each tile is a fixed-width segment of a single progress bar, clearly communicating a simple binary state (read vs not-yet) and applying an accent border or glow to today
  - Visual system holds up in light and dark modes without bespoke artwork
  - Color and iconography choices meet contrast guidelines in light and dark modes

## Story 3 – Milestone Celebrations
- **As** a reader building a habit
- **I want** milestone celebrations when I hit meaningful thresholds (4 days, 7 days)
- **So that** I feel rewarded for progress and motivated to keep going
- **Acceptance**
  - Hitting day four updates the headline and surfaces a reserved success badge or color accent—encouraging but not over-the-top
  - Completing seven days elevates the card with a bold celebration banner and energized encouragement copy
  - If data rolls back below the milestone, the card reverts without errors or stale copy

## Story 4 – Contextual Encouragement
- **As** a reader who sometimes falls behind
- **I want** the widget to adapt its tone based on my current progress
- **So that** the encouragement feels personal rather than generic
- **Acceptance**
  - Copy variants cover three trajectories: ahead/on-pace, slightly behind, and off-track
  - Messages stay generic enough to ship without pulling extra passage metadata
  - Empty-state copy nudges me toward logging a first reading with an encouraging tone

## Story 5 – Mobile-First Responsiveness
- **As** a reader primarily using my phone
- **I want** the Weekly Journey card to feel native on small screens without losing detail on desktop
- **So that** the experience is consistent wherever I check in
- **Acceptance**
  - Mobile layout trims spacing and stacks header, segmented bar, and summary footer without crowding while keeping all seven tiles visible
  - Desktop layout reuses the same structure with generous spacing and clear tap/click affordances
  - Card respects existing dashboard breakpoints and avoids layout shifts when HTMX refreshes content

## Story 6 – Data Integrity & Resilience
- **As** the product team shipping the refresh
- **I want** the widget to stay accurate and resilient to partial data
- **So that** we maintain trust even with limited telemetry
- **Acceptance**
  - Distinct-day counts remain the primary progress metric; design accommodates lack of time-on-page data
  - Widget degrades gracefully when readings lack passage text or notes
  - Loading/error states follow existing dashboard behavior (server renders full widget; use standard error fallback if stats fail)
