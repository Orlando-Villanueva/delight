# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Delight** is a Laravel-based Bible reading habit tracker designed to help users establish and maintain consistent Bible reading habits. The core loop is: Read → Log → See Progress → Motivation to Read Again.

**Tech Stack:**
- **Backend:** Laravel 12 with Service Layer Pattern
- **Frontend:** HTMX + Alpine.js with server-driven architecture
- **Database:** SQLite (local development), PostgreSQL (production via Laravel Cloud)
- **Authentication:** Laravel Fortify with standard Laravel forms
- **Styling:** Tailwind CSS 4.0
- **Testing:** Pest (PHPUnit)

## Common Development Commands

### Laravel Development
```bash
# Database operations
php artisan migrate
php artisan migrate:fresh --seed
php artisan migrate:status

# Testing
php artisan test
vendor/bin/pest
composer test

# Development utilities  
php artisan tinker
php artisan route:list
php artisan config:show
php artisan db:seed

# Asset building
npm run dev
npm run build
```

### **IMPORTANT: Development Server Constraint**
- **NEVER use `php artisan serve`** - This project uses Laravel Herd for local development
- Laravel Herd provides native web server with .test domains
- Use Laravel Herd's GUI or configured local domains instead
- For testing, use `php artisan test`, route inspection, or Herd's built-in server
- **Note:** `composer dev` script exists but contradicts this policy - use Herd instead

### CSS Development
```bash
# Watch for changes during development (with hot reload)
npm run dev

# Build for production
npm run build
```

### Code Quality
```bash
# Code formatting (if configured)
./vendor/bin/pint

# Static analysis (if configured)  
./vendor/bin/phpstan analyse
```

## Architecture & Code Organization

### Service Layer Pattern
The application implements a clean Service Layer Pattern for business logic separation:

```
app/
├── Services/
│   ├── ReadingLogService.php         # Core reading log business logic
│   ├── UserStatisticsService.php     # Dashboard statistics & analytics  
│   ├── BookProgressService.php       # Bible book completion tracking
│   ├── BookProgressSyncService.php   # Book progress synchronization
│   ├── BibleReferenceService.php     # Bible reference validation & formatting
│   ├── StreakStateService.php        # Streak calculation logic
│   ├── ReadingFormService.php        # Reading form handling & validation
│   ├── WeeklyGoalService.php         # Weekly reading goal management
│   └── EmailService.php              # Email notifications & communication
├── Http/Controllers/              # Thin controllers delegate to services
├── Models/                        # Eloquent models (User, ReadingLog, BookProgress)
└── View/Components/               # Blade components for UI
```

### Key Services
- **ReadingLogService:** Handles reading log creation, validation, and HTMX responses
- **UserStatisticsService:** Calculates dashboard statistics, streaks, and progress summaries  
- **BibleReferenceService:** Validates Bible references and formats book/chapter data
- **BookProgressService:** Manages denormalized book completion tracking
- **BookProgressSyncService:** Synchronizes book progress across user data
- **ReadingFormService:** Handles reading form processing and validation
- **WeeklyGoalService:** Manages weekly reading goals and progress tracking
- **EmailService:** Handles email notifications and user communications

### HTMX-Native Architecture
- Server returns HTML fragments, not JSON
- Uses `hx-*` attributes for interactions
- Follows Zero-Duplication Component Pattern
- Controllers support both HTMX and standard Laravel request handling

## Database Schema

### Core Tables
```sql
users (id, name, email, password, timestamps)
reading_logs (id, user_id, book_id, chapter, passage_text, date_read, notes_text, timestamps)  
book_progress (id, user_id, book_id, book_name, total_chapters, chapters_read, completion_percent, is_completed, last_updated)
```

### Bible Reference System
- Static configuration in `config/bible.php` (66 books, chapter counts)
- Translations in `lang/en/bible.php` and `lang/fr/bible.php`
- BibleReferenceService handles lookups and validation

## Development Standards

### Code Quality Rules
1. **Always use proper imports** - never fully qualified class names in code
   - ❌ `new \Illuminate\Support\Collection()`
   - ✅ `use Illuminate\Support\Collection;` then `new Collection()`

2. **Never use raw SQL methods** - always use Eloquent and query builder
   - ❌ `selectRaw()`, `whereRaw()`, `havingRaw()`
   - ✅ Eloquent collection methods and Laravel's built-in functions

3. **Alpine.js organization** - extract complex logic from inline x-data
   - ❌ `x-data="{ complex: 'logic here' }"`
   - ✅ `x-data="componentFunction()"` with separate `<script>` tag

### Frontend Standards
- **Tailwind CSS only** - no custom CSS except for framework requirements
- **HTMX-native approach** - use built-in HTMX attributes over JavaScript
- **Progressive enhancement** - features work without JavaScript
- **Mobile-first responsive design**

### Testing Approach
- **Essential verification over comprehensive coverage** for MVP
- Focus on core user workflow: register → log reading → view progress  
- Test streak calculations, Bible reference validation, book progress updates
- Use Pest for unit and feature tests
- **Testing Database:** Uses `database/testing.sqlite` (separate from development database)

## Key Features & Business Logic

### Streak Calculation
- **1-day grace period:** Streak continues if user reads today OR yesterday
- Current streak: consecutive days from present backwards
- Longest streak: maximum consecutive days in user's history
- Timezone-aware date handling

### Bible Reference Handling  
- 66 books with configurable chapter counts
- Whole chapters only (no verse-level tracking in MVP)
- Multi-language support (English/French)
- Structured selectors prevent invalid references

### Book Progress Tracking
- Denormalized `book_progress` table for performance
- Updated incrementally with each reading log
- Tracks chapters read per book as JSON array
- Calculates completion percentages automatically

## Important Project Rules

### Development Environment
- **Use Laravel Herd for local development** - never `php artisan serve`
- SQLite for local development, PostgreSQL for production
- Standard Laravel forms with Fortify authentication (not complex HTMX auth)

### Code Implementation
- Follow Service Layer Pattern - thin controllers, business logic in services
- HTMX returns HTML fragments, controllers handle content negotiation
- Use event-based cache invalidation for user statistics
- Implement proper error handling and validation

### Documentation & Issue Management
- Always check `/docs` directory for established requirements before implementing
- Validate requests against Product Requirements Document
- Never implement features outside documented scope without explicit approval
- Preserve original tasks in Linear issues - don't remove, only mark complete

### Development Guidelines (Post-MVP, ~10 Active Users)
- **Scale Reality Check:** Currently optimizing for 10-100 users, not enterprise scale
- **Time Management:** Balance feature development with user feedback and bug fixes
- **Framework Trust:** Leverage Laravel's built-in solutions for reliability
- **User Impact Focus:** Prioritize features that improve the core reading habit loop
- **Essential Testing:** Focus on functionality that affects real user workflows

### Linear Issue Management
- **Wait for user testing confirmation** before updating Linear issues with completion status
- Allow users to test code changes and verify functionality before marking tasks complete
- Only update Linear issues after receiving explicit user confirmation that changes work correctly

## File Structure Guide

### Views (Zero-Duplication Pattern)
```
resources/views/
├── partials/                    # Shared reusable components
│   ├── dashboard-content.blade.php
│   ├── reading-log-form.blade.php  
│   └── header-update.blade.php
├── components/                  # Blade components
│   ├── ui/ (button, card, input)
│   └── bible/ (book-selector, progress-stats)
└── dashboard.blade.php          # Main views use @include
```

### Key Configuration Files
- `config/bible.php` - Bible book structure and chapter counts
- `lang/*/bible.php` - Localized Bible book names
- `phpunit.xml` - Test configuration with SQLite testing database
- `vite.config.js` - Asset building with Tailwind CSS

## Performance Considerations

### Caching Strategy
- Dashboard statistics cached with 5-minute TTL
- Streak calculations cached with 15-minute TTL  
- Bible reference data cached for 24 hours
- Event-based cache invalidation on new reading logs

### Database Optimization
- Composite indexes on `(user_id, date_read)` for calendar queries
- Denormalized book_progress table reduces complex joins
- Query optimization for streak calculations
- Production PostgreSQL hosted on Laravel Cloud

## Common Patterns

### Controller Pattern
```php
public function store(Request $request) 
{
    $result = $this->readingLogService->logReading(
        $request->user(), 
        $request->validated()
    );
    
    if ($request->header('HX-Request')) {
        return view('partials.reading-log-success', compact('result'));
    }
    
    return redirect()->back()->with('success', 'Reading logged!');
}
```

### Service Pattern
```php
class ReadingLogService 
{
    public function __construct(
        private BibleReferenceService $bibleService,
        private BookProgressService $progressService
    ) {}
    
    public function logReading(User $user, array $data): ReadingLog
    {
        // Business logic here
        // Update book progress
        // Clear relevant caches
    }
}
```

This architecture enables rapid development while maintaining clean separation of concerns and high testability.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.11
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/telescope (TELESCOPE) - v5
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- tailwindcss (TAILWINDCSS) - v4


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff"
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
- Just remember to run the watch commands or remind me to
