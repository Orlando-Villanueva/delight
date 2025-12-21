# Repository Guidelines

## Project Structure & Module Organization
- `app/` houses domain logic; controllers in `app/Http`, service classes in `app/Services`, and action objects in `app/Actions` for discrete workflows.
- UI is server-driven: Blade layouts and HTMX-ready partials live in `resources/views`; Flowbite components under `resources/views/components`.
- Frontend helpers live in `resources/js`; Tailwind tokens in `resources/css`.
- HTTP entry points are defined in `routes/web.php`, while background tasks and listeners live in `app/Console` and `app/Listeners`.
- Record decisions in `docs/` and pair schema changes with matching migrations and seeders.

## Build, Test, and Development Commands
- `composer install` / `npm install` – install PHP and JS dependencies.
- `php artisan migrate --seed` – apply schema changes and load Bible data.
- `npm run dev` – launch the Vite dev server; pair with Herd or `php artisan serve`.
- `npm run build` – compile production-ready assets.
- `php artisan test` or `composer test` – run the Pest-powered suite.

## Coding Style & Naming Conventions
- Follow PSR-12 with 4-space indentation (`.editorconfig` enforced); run `./vendor/bin/pint` before opening a pull request.
- Structure services as `App\Services\{Domain}Service`; keep action classes verb-oriented (e.g., `LogDailyReading`).
- Blade files should stay HTMX-first: prefer `hx-*` attributes and use **Blade Fragments** (`@fragment`) for partial page updates over separate partial files.
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
