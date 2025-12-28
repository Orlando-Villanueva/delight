# Repository Guidelines

## Project Structure & Module Organization
- `app/` houses domain logic; controllers in `app/Http`, service classes in `app/Services`, and action objects in `app/Actions` for discrete workflows.
- UI is server-driven: Blade layouts and HTMX-ready partials live in `resources/views`; Flowbite components under `resources/views/components`.
- Frontend helpers live in `resources/js`; Tailwind tokens in `resources/css`.
- HTTP entry points are defined in `routes/web.php`, while background tasks and listeners live in `app/Console` and `app/Listeners`.
- Record decisions in `docs/` and pair schema changes with matching migrations and seeders.

## Annual Recap Year Roll-Forward
- See `docs/annual-recap/README.md` for the yearly setup checklist and examples.

## Build, Test, and Development Commands
- `composer install` / `npm install` – install PHP and JS dependencies.
- `php artisan migrate --seed` – apply schema changes and load Bible data.
- `npm run dev` – launch the Vite dev server (handles Tailwind v4); pair with Herd.
- `npm run build` – compile production-ready assets.
- `php artisan test` or `composer test` – run the Pest-powered suite.
- `mailpit` – run the local email server (view at http://localhost:8025).

## Telescope (Local Profiling)
- Telescope is only registered in the `local` environment; ensure `APP_ENV=local`.
- Enable it with `TELESCOPE_ENABLED=true` in `.env`, then run `php artisan migrate` if tables are missing.
- Visit `http://delight.test/telescope` (or `/{TELESCOPE_PATH}`) once enabled.
- For non-local access, add allowed emails via `TELESCOPE_ADMIN_EMAILS` (comma-separated).
- Use Telescope to inspect slow requests, N+1 queries, failing jobs, and unexpected cache/queue behavior during feature work or debugging regressions.
- When reviewing a PR or QAing a flow, check Telescope for errors, repeated queries, or excessive response times before shipping.

## Visual Language & UI Design
- **Aesthetics**: Aim for a "Premium & Focused" feel. Use rounded-xl (12px) for cards and rounded-lg (8px) for inputs.
- **Color Palette**: 
    - Dark mode is primary: Cards use `dark:bg-gray-800` with `dark:border-gray-700`.
    - Accent colors: Primary Blue (`blue-600`), Success Green, Warning Yellow.
- **Flowbite**: Most complex components (Drawers, Modals, Dropdowns) are based on Flowbite. Refer to `resources/views/components/ui` for local wrappers.
- **Responsiveness**: Always test mobile (mobile-first approach). Many users log readings on the go.

## Coding Style & Naming Conventions
- Follow PSR-12 with 4-space indentation; run `./vendor/bin/pint` before committing.
- Structure services as `App\Services\{Domain}Service`; keep action classes verb-oriented.
- Blade files should stay HTMX-first: prefer `hx-*` attributes and use **Blade Fragments** (`@fragment`) for partial page updates over separate partial files.
- Admin Styling: Align all admin forms with the "Feedback Form" style found in `resources/views/partials/feedback-form.blade.php`.
- Lean on Tailwind utilities; avoid custom CSS unless a technical constraint demands it.

## Testing Guidelines
- Pest configuration lives in `tests/`; add Feature tests for HTTP flows and Unit tests for service-layer logic.
- Name Pest closures in the `it_can_*` style and mirror production namespaces under `tests/Feature/App/...`.
- When touching reading log or streak logic, add regression coverage for grace-period edge cases.
- For database changes, run against the SQLite `.env.testing` database with `php artisan test --parallel`.

## Commit & Pull Request Guidelines
- Messages start with the Linear ticket: `[DEL-###] Short imperative summary`; keep bodies intent-focused.
- Squash noisy WIP commits before pushing and rebase onto `main` for clean history.
- Pull requests include problem statement, implementation notes, verification steps (`php artisan test`, `npm run build` if assets changed), and UI screenshots for Blade updates.
- Link related documentation updates or call out the `docs/` references consulted so reviewers can trace decisions.

## Environment & Configuration Tips
- Copy `.env.example` to `.env`, set `APP_URL` to your Herd domain, and point `DB_DATABASE` to `database/database.sqlite`.
- Never commit `.env` or generated storage artifacts; keep local-only files in `storage/` and reference assets in `screenshots/`.
