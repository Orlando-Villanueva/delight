---
name: testing
description: Pest testing patterns, conventions, and coverage expectations for this Laravel project
---

# Testing Skill

## Test Framework & Style

**All tests use Pest** (not PHPUnit class-style). Use Pest function-style syntax exclusively.

### Naming Convention
- Name test descriptions in the `can_*` style: `it('can calculate onboarding rate correctly', function () { ... }`)

### Required Structure
- Use `beforeEach()` for shared setup
- Use `afterEach()` for cleanup (e.g., `Carbon::setTestNow()`)
- Group related tests with `describe()` blocks for logical organization
- Use `uses(Tests\TestCase::class, RefreshDatabase::class)` at the top of test files that need database access

## Test Types & When to Use

| Type | Location | Purpose |
|------|----------|---------|
| **Feature** | `tests/Feature/App/...` | HTTP flows, controller responses, FormRequest validation |
| **Unit** | `tests/Unit/` | Service-layer logic, isolated business rules |

### Namespace Mirroring
Mirror production namespaces:
- `App\Http\Controllers\Admin\AnalyticsController` → `tests/Feature/App/Http/Controllers/Admin/AnalyticsControllerTest.php`

### Feature vs Unit
- Feature tests = endpoint coverage: exercise authorization, validation, and response structure
- Unit tests = service methods with mocked or real database state as appropriate

## Coverage Expectations

Aim for decent code coverage on all new implementations.

### New Service Tests Must Cover
- Empty/zero state behavior
- Normal operation with typical data
- Edge cases and boundary conditions
- Error handling paths

### New Controller Endpoint Tests Must Cover
- Authenticated vs. guest access
- Authorization (admin-only, owner-only, etc.)
- Successful responses with expected data
- Validation failures

## Time-Sensitive Tests

- **Always** reset Carbon after tests that mock time: `Carbon::setTestNow()` in `afterEach()`
- Flush cache in `beforeEach()` and `afterEach()` for tests involving cached data

## Domain-Specific Coverage

- When touching reading log or streak logic, add regression coverage for grace-period edge cases
- For database schema changes, run against SQLite `.env.testing` with `php artisan test --parallel`

## Example Pest Test Structure

```php
<?php

use App\Services\MyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->service = app(MyService::class);
});

afterEach(function () {
    Cache::flush();
    Carbon::setTestNow();
});

describe('Feature Group', function () {
    it('can do something correctly', function () {
        // Arrange
        Carbon::setTestNow('2026-02-10 12:00:00');
        $user = User::factory()->create();

        // Act
        $result = $this->service->doSomething($user);

        // Assert
        expect($result)->toBe('expected');
    });

    it('can handle empty state', function () {
        $result = $this->service->doSomething(null);

        $this->assertSame(0, $result);
    });
});

describe('Edge Cases', function () {
    it('can handle boundary conditions', function () {
        // Test threshold boundaries (exactly 80%, exactly 24h, etc.)
    });
});
```

## Anti-Patterns to Avoid

- **Never** use PHPUnit class-style (`class MyTest extends TestCase`)
- **Never** skip `afterEach()` cleanup for time-mocked tests
- **Never** use `rand()` in assertions (causes flaky tests)
- **Never** forget to flush cache between tests that use caching
