# Laravel 12 Codebase Audit Prompt

Perform a comprehensive Laravel 12 full codebase audit of this repository.

## Scope (include)

`app/`, `routes/`, `config/`, `database/`, `tests/`, `resources/` (Blade views/components, HTMX usage, Alpine.js, Tailwind CSS), `public/` assets that are part of the app, and `package.json`/Vite config only insofar as it affects the app’s frontend build (no infra/hosting).

## Out of scope (exclude)

Infrastructure/deployment (Laravel Cloud config, server config, Docker, CI/CD), unless required to explain an app issue.

## Goal

Identify and implement exactly one safe improvement or fix (a small refactor or best‑practice upgrade is acceptable), chosen from these areas: correctness, security, architecture/design, performance, maintainability/DevEx, UX/accessibility, frontend reliability, CSS quality, documentation/ops hygiene, or observability.

## Process

Scan the repo to map major features/modules, request flows, auth, main models, and hotspots in controllers, queries, Blade, and JS. Select the single best improvement you can safely implement with minimal churn. Implement it. If a potential audit area isn’t wired up yet, note that briefly and move on without blocking the review.

## Output format (no lists)

Write short paragraphs with labels. Include: Executive summary, Chosen improvement (title, location, why it matters, what changed), Example patch/snippet, Tests/commands run (or explicitly state what was not run and why).

## Change policy

You may implement fixes/refactors directly; keep changes small and reviewable. After each batch of changes, run `./vendor/bin/pint`, backend tests (`phpunit` or `pest`, whichever configured), and the frontend build/test if available (`npm test` if present; otherwise `npm run build`). Do not change behavior without stating it explicitly and adding/updating tests. Do not invent missing context; if uncertain, label assumptions and suggest how to verify. Prefer Laravel-native patterns and clean Blade component boundaries.

## Success criteria

Implement exactly one safe improvement/fix and show evidence via passing Pint + tests (and a successful frontend build if applicable).
