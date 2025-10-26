<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Navigation Component Rendering', function () {
    it('renders desktop sidebar navigation for authenticated users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('Dashboard');
        $response->assertSee('Log Reading');
        $response->assertSee('History');
    });

    it('renders desktop navbar with logo and user profile', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee(config('app.name'));
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');
        $response->assertSee('Sign out');
    });

    it('renders mobile bottom navigation bar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        // Check for sr-only labels in mobile navigation
        $response->assertSee('Dashboard', false);
        $response->assertSee('History', false);
    });

    it('displays user initial in profile avatar', function () {
        $user = User::factory()->create([
            'name' => 'Alice Smith',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        // Should display first letter of name
        $response->assertSee('A', false);
    });

    it('renders user avatar image when an avatar URL is set', function () {
        $user = User::factory()->create([
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('https://example.com/avatar.jpg', false);
        $response->assertSee('referrerpolicy="no-referrer"', false);
    });

    it('renders Log Reading button in desktop navbar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('Log Reading');
        $response->assertSee(route('logs.create'));
    });
});

describe('HTMX Navigation Requests', function () {
    it('returns partial content for HTMX dashboard request', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard', [
            'HX-Request' => 'true',
        ]);

        $response->assertSuccessful();
        // HTMX requests should return partial content without full layout
        $response->assertDontSee('<html>', false);
        $response->assertDontSee('<!DOCTYPE', false);
    });

    it('returns partial content for HTMX log reading form request', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logs/create', [
            'HX-Request' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertDontSee('<html>', false);
        $response->assertDontSee('<!DOCTYPE', false);
    });

    it('returns partial content for HTMX history request', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logs', [
            'HX-Request' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertDontSee('<html>', false);
        $response->assertDontSee('<!DOCTYPE', false);
    });

    it('returns full layout for standard dashboard request', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        // Standard requests should include full HTML layout
        $response->assertSee('<html', false);
        $response->assertSee('<!DOCTYPE', false);
    });

    it('includes HTMX attributes in navigation links', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hx-get', false);
        $response->assertSee('hx-target', false);
        $response->assertSee('hx-swap', false);
        $response->assertSee('hx-push-url', false);
    });

    it('targets correct page container element', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hx-target="#page-container"', false);
        $response->assertSee('id="page-container"', false);
    });
});

describe('Navigation Routes', function () {
    it('navigates to dashboard successfully', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('Dashboard');
    });

    it('navigates to log reading form successfully', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('logs.create'));

        $response->assertSuccessful();
    });

    it('navigates to reading history successfully', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('logs.index'));

        $response->assertSuccessful();
    });

    it('redirects unauthenticated users from dashboard to login', function () {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    });

    it('redirects unauthenticated users from log reading to login', function () {
        $response = $this->get(route('logs.create'));

        $response->assertRedirect(route('login'));
    });

    it('redirects unauthenticated users from history to login', function () {
        $response = $this->get(route('logs.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('Navigation Logout Functionality', function () {
    it('logs out user successfully via navigation logout button', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    });

    it('includes CSRF token in logout form', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('name="_token"', false);
    });

    it('logout button is only accessible to authenticated users', function () {
        $response = $this->post(route('logout'));

        // Should redirect to login if not authenticated
        $response->assertRedirect(route('login'));
    });
});

describe('Dark Mode Support', function () {
    it('includes dark mode classes in desktop sidebar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('dark:bg-gray-800', false);
        $response->assertSee('dark:text-white', false);
        $response->assertSee('dark:hover:bg-gray-700', false);
    });

    it('includes dark mode classes in desktop navbar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('dark:bg-gray-800', false);
        $response->assertSee('dark:border-gray-700', false);
    });

    it('includes dark mode classes in mobile bottom bar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('dark:bg-gray-700', false);
        $response->assertSee('dark:border-gray-600', false);
    });
});

describe('Responsive Design', function () {
    it('hides desktop sidebar on mobile with lg breakpoint', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hidden lg:flex', false);
    });

    it('hides mobile bottom bar on desktop with lg breakpoint', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('lg:hidden', false);
    });

    it('shows Log Reading button only on desktop', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hidden lg:inline-flex', false);
    });
});

describe('Accessibility Features', function () {
    it('includes sr-only labels for mobile navigation icons', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('sr-only', false);
    });

    it('includes aria-hidden on decorative SVG icons', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('aria-hidden="true"', false);
    });

    it('includes aria-expanded attribute on dropdown button', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('aria-expanded', false);
    });

    it('includes role attributes in dropdown menu', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('role="none"', false);
        $response->assertSee('role="menuitem"', false);
    });
});

describe('Navigation URL Management', function () {
    it('uses named routes for navigation links', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee(route('dashboard'));
        $response->assertSee(route('logs.create'));
        $response->assertSee(route('logs.index'));
    });

    it('includes hx-push-url attribute for browser history', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hx-push-url="true"', false);
    });
});

describe('Navigation Component Integration', function () {
    it('includes page container div for HTMX content swapping', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('id="page-container"', false);
    });

    it('loads all navigation components on authenticated pages', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        // Check that key navigation elements from all components are present
        $response->assertSee('<nav', false); // Desktop navbar
        $response->assertSee('<aside', false); // Desktop sidebar
        $response->assertSee('rounded-full bottom-4', false); // Mobile bottom bar
    });

    it('includes browser navigation support script', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        // HTMX handles history automatically, check for history event listener
        $response->assertSee('htmx:historyRestore', false);
        $response->assertSee('HTMX History Configuration', false);
    });
});

describe('Brand Styling', function () {
    it('uses primary color for profile avatar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('bg-primary-500', false);
        $response->assertSee('focus:ring-primary-300', false);
    });

    it('uses accent color for Log Reading button', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('bg-accent-500', false);
        $response->assertSee('hover:bg-accent-600', false);
    });

    it('uses primary color for hover states in sidebar', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertSee('hover:bg-primary-50', false);
    });
});
