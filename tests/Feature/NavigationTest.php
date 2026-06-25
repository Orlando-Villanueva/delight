<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const AUTHENTICATED_SHELL_SCRIPT = 'js/components/authenticated-shell.js';

beforeEach(function () {
    $this->getDashboard = fn (?User $user = null) => $this->actingAs($user ?? User::factory()->create())->get('/dashboard');
    $this->getHtmx = fn (string $path, ?User $user = null) => $this->actingAs($user ?? User::factory()->create())->get($path, [
        'HX-Request' => 'true',
    ]);
});

describe('Navigation Component Rendering', function () {
    it('renders desktop sidebar navigation for authenticated users', function () {
        $response = ($this->getDashboard)();

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

        $response = ($this->getDashboard)($user);

        $response->assertSuccessful();
        $response->assertSee(config('app.name'));
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');
        $response->assertSee('Sign out');
    });

    it('renders mobile bottom navigation bar', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        // Check for sr-only labels in mobile navigation
        $response->assertSee('Dashboard', false);
        $response->assertSee('History', false);
        $response->assertSee('content="width=device-width, initial-scale=1, viewport-fit=cover"', false);
        $response->assertSee('px-mobile-safe pt-mobile-safe pb-3', false);
        $response->assertSee('z-stack-nav', false);
        $response->assertSee('bottom-mobile-nav-safe', false);
        $response->assertSee('pb-mobile-nav-safe lg:pb-4', false);
    });

    it('defines safe area utilities for mobile navigation spacing', function () {
        $css = file_get_contents(resource_path('css/app.css'));

        expect($css)
            ->toContain('.bottom-mobile-nav-safe')
            ->toContain('bottom: calc(1rem + env(safe-area-inset-bottom, 0px));')
            ->toContain('.pb-mobile-nav-safe')
            ->toContain('padding-bottom: calc(6rem + env(safe-area-inset-bottom, 0px));')
            ->toContain('@media (width >= 64rem)')
            ->toContain('padding-bottom: 1rem;');
    });

    it('centers the log reading action in the mobile bottom navigation order', function () {
        $response = ($this->getDashboard)();

        $content = substr($response->getContent(), strpos($response->getContent(), 'id="mobile-bottom-navigation"'));

        expect(strpos($content, 'id="mobile-plans-link"'))
            ->toBeLessThan(strpos($content, 'hx-get="'.route('logs.create').'"'))
            ->and(strpos($content, 'hx-get="'.route('logs.create').'"'))
            ->toBeLessThan(strpos($content, 'hx-get="'.route('logs.index').'"'))
            ->and(strpos($content, 'hx-get="'.route('logs.index').'"'))
            ->toBeLessThan(strpos($content, 'hx-get="'.route('achievements.index').'"'));
    });

    it('displays user initial in profile avatar', function () {
        $user = User::factory()->create([
            'name' => 'Alice Smith',
        ]);

        $response = ($this->getDashboard)($user);

        $response->assertSuccessful();
        // Should display first letter of name
        $response->assertSee('A', false);
    });

    it('renders user avatar image when an avatar URL is set', function () {
        $user = User::factory()->create([
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        $response = ($this->getDashboard)($user);

        $response->assertSuccessful();
        $response->assertSee('https://example.com/avatar.jpg', false);
        $response->assertSee('referrerpolicy="no-referrer"', false);
    });

    it('renders Log Reading button in desktop navbar', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        $response->assertSee('Log Reading');
        $response->assertSee(route('logs.create'));
    });

    it('renders collapsible desktop sidebar controls without persisted browser storage', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        expect($response->getContent())
            ->toContain('x-data="authenticatedShell()"')
            ->toContain('x-on:click="toggleSidebar()"')
            ->toContain('aria-controls="desktop-sidebar-navigation"')
            ->toContain('Expand sidebar')
            ->toContain('Collapse sidebar')
            ->not->toContain('currentSidebarPath: window.location.pathname')
            ->not->toContain('aria-label="{{ $label }}"')
            ->not->toContain('compactSidebarQuery: null')
            ->not->toContain('localStorage')
            ->not->toContain('sessionStorage');

        $authenticatedShell = file_get_contents(resource_path(AUTHENTICATED_SHELL_SCRIPT));

        expect($authenticatedShell)
            ->toContain('export const authenticatedShell')
            ->toContain('sidebarCollapsed: false')
            ->toContain('sidebarUserToggled: false')
            ->toContain("matchMedia('(min-width: 1024px) and (max-width: 1279.98px)')")
            ->not->toContain('localStorage')
            ->not->toContain('sessionStorage');
    });
});

describe('HTMX Navigation Requests', function () {
    it('returns partial content for :dataset requests', function (string $path) {
        $response = ($this->getHtmx)($path);
        $response->assertSuccessful();
        $response->assertDontSee('<html>', false);
        $response->assertDontSee('<!DOCTYPE', false);
    })->with([
        'dashboard' => '/dashboard',
        'log reading form' => '/logs/create',
        'history' => '/logs',
    ]);

    it('returns full layout for standard dashboard request', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        // Standard requests should include full HTML layout
        $response->assertSee('<html', false);
        $response->assertSee('<!DOCTYPE', false);
    });

    it('renders :dataset in the standard dashboard layout', function (array $expectedMarkup) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();

        foreach ($expectedMarkup as $markup) {
            $response->assertSee($markup, false);
        }
    })->with([
        'HTMX navigation attributes' => [['hx-get', 'hx-target', 'hx-swap', 'hx-push-url']],
        'page container target' => [['hx-target="#page-container"', 'id="page-container"']],
    ]);

    it('renders global page navigation loading feedback without replacing form save indicators', function () {
        $user = User::factory()->create();

        $dashboardResponse = ($this->getDashboard)($user);

        $dashboardResponse->assertSuccessful();
        $dashboardResponse->assertSee('<progress', false);
        $dashboardResponse->assertSee('id="page-navigation-loading"', false);
        $dashboardResponse->assertSee('data-page-navigation-loading', false);
        $dashboardResponse->assertSee('max="100"', false);
        $dashboardResponse->assertSee('value="0"', false);

        $script = file_get_contents(resource_path('js/app.js'));

        expect($script)
            ->toContain("document.getElementById('page-navigation-loading')")
            ->toContain("target?.id === 'page-container'")
            ->toContain('pageNavigationLoading.value = 70')
            ->toContain('pageNavigationLoading.value = 100')
            ->toContain("document.body.addEventListener('htmx:beforeRequest'")
            ->toContain("document.body.addEventListener('htmx:afterRequest', hideIfPageContainerRequest)");

        $response = $this->actingAs($user)->get(route('logs.create'));

        $response->assertSuccessful();
        $response->assertSee('hx-indicator="#save-loading"', false);
        $response->assertSee('id="save-loading"', false);
        $response->assertSee('htmx-indicator-hidden">Log Reading', false);
    });

    it('wires offline retry buttons to reset scroll before reloading', function () {
        $script = file_get_contents(resource_path('js/app.js'));

        expect($script)
            ->toContain('[data-offline-retry]')
            ->toContain("history.scrollRestoration = 'manual'")
            ->toContain("globalThis.scrollTo({ top: 0, left: 0, behavior: 'auto' })")
            ->toContain("document.querySelector('main.flex-1')?.scrollTo({ top: 0, left: 0 })")
            ->toContain('globalThis.location.replace(globalThis.location.href)')
            ->not->toContain('navigator.onLine');
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
        $response->assertSee('Log Reading');
        $response->assertSee("Record today's Bible reading.");
    });

    it('always shows today and yesterday on the log form', function () {
        $user = User::factory()->create([
            'created_at' => today()->subMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('logs.create'));

        $response->assertSuccessful();
        $response->assertSeeText('When did you read?');
        $response->assertSeeText('Yesterday');
        $response->assertSeeText('Today');
        $response->assertSeeText('Forgot to log? Choose yesterday.');
        $response->assertSee('data-date-read-segmented-control', false);
        $response->assertDontSee('Logging for today', false);
        $response->assertDontSee('Grace period help', false);
        $this->assertMatchesRegularExpression('/for="today".*?Today.*?for="yesterday".*?Yesterday/s', $response->getContent());
        $this->assertMatchesRegularExpression('/id="today".*?checked/s', $response->getContent());
        $this->assertMatchesRegularExpression('/class="[^"]*peer-checked:bg-primary-50[^"]*peer-checked:text-primary-700[^"]*peer-focus-visible:ring-2/', $response->getContent());
        $response->assertDontSee('peer-checked:ring-1', false);
        $response->assertDontSee('peer-checked:ring-primary-500', false);
    });

    it('keeps yesterday available when yesterday is already logged', function () {
        $user = User::factory()->create([
            'created_at' => today()->subMonth(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => today()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('logs.create'));

        $response->assertSuccessful();
        $response->assertSeeText('When did you read?');
        $response->assertSeeText('Yesterday');
        $response->assertSeeText('Today');
        $response->assertSeeText('Forgot to log? Choose yesterday.');
        $response->assertDontSee('Logging for today', false);
    });

    it('navigates to reading history successfully', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('logs.index'));

        $response->assertSuccessful();
        $response->assertSee('Reading History');
        $response->assertSee('Review and manage past readings.');
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
        $response = ($this->getDashboard)();

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
    it('includes dark mode classes for the :dataset', function (array $expectedClasses) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();

        foreach ($expectedClasses as $class) {
            $response->assertSee($class, false);
        }
    })->with([
        'desktop sidebar' => [['dark:bg-gray-800', 'dark:text-white', 'dark:hover:bg-gray-700']],
        'desktop navbar' => [['dark:bg-gray-800', 'dark:border-gray-700']],
        'mobile bottom bar' => [['dark:bg-gray-700', 'dark:border-gray-600']],
    ]);
});

describe('Responsive Design', function () {
    it('renders the :dataset responsive class', function (string $responsiveClass) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();
        $response->assertSee($responsiveClass, false);
    })->with([
        'desktop sidebar visibility' => 'hidden lg:flex',
        'mobile bottom bar visibility' => 'lg:hidden',
        'desktop Log Reading button visibility' => 'hidden lg:inline-flex',
    ]);

    it('defaults the desktop sidebar to a compact rail below xl and expanded at xl', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        expect($response->getContent())
            ->toContain('w-20 xl:w-64')
            ->toContain("sidebarCollapsed ? '!w-20' : '!w-64'")
            ->toContain('data-sidebar-icon-slot')
            ->toContain("sidebarCollapsed ? '!max-w-0 !opacity-0 !ms-0' : '!max-w-40 !opacity-100 !ms-1'")
            ->not->toContain("'justify-center': sidebarIconRail()")
            ->not->toContain("sidebarCollapsed ? 'sr-only' : ''");
    });

    it('keeps the desktop sidebar icon column fixed while labels animate', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        $content = $response->getContent();
        $authenticatedShell = file_get_contents(resource_path(AUTHENTICATED_SHELL_SCRIPT));

        expect($authenticatedShell)
            ->toContain('setSidebarCollapsed(shouldCollapse)')
            ->not->toContain('sidebarIconRail()')
            ->not->toContain('sidebarIconRailActive');

        expect($content)
            ->toContain('data-sidebar-icon-slot')
            ->toContain('inline-flex h-6 w-10 shrink-0 items-center justify-center')
            ->not->toContain("'justify-center': sidebarIconRail()")
            ->toContain('max-w-0 overflow-hidden whitespace-nowrap opacity-0')
            ->toContain('xl:ms-1 xl:max-w-40 xl:opacity-100')
            ->toContain('transition-[max-width,opacity,margin]')
            ->toContain('motion-reduce:transition-none')
            ->toContain("sidebarCollapsed ? '!max-w-0 !opacity-0 !ms-0' : '!max-w-40 !opacity-100 !ms-1'")
            ->not->toContain('x-bind:aria-hidden="sidebarCollapsed.toString()"');
    });

    it('keeps the desktop active navigation state synchronized with HTMX history', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        expect($response->getContent())
            ->toContain('x-data="authenticatedShell()"')
            ->toContain("isSidebarPathActive('/dashboard', false)")
            ->toContain("isSidebarPathActive('/plans', true)");

        $authenticatedShell = file_get_contents(resource_path(AUTHENTICATED_SHELL_SCRIPT));

        expect($authenticatedShell)
            ->toContain('currentSidebarPath: globalThis.location.pathname')
            ->toContain("document.body.addEventListener('htmx:pushedIntoHistory', syncSidebarPath)")
            ->toContain("document.body.addEventListener('htmx:historyRestore', syncSidebarPath)")
            ->toContain("globalThis.addEventListener('popstate', syncSidebarPath)")
            ->toContain('isSidebarPathActive(targetPath, matchPrefix)')
            ->toContain("if (!matchPrefix || normalizedTargetPath === '/')");
    });

    it('matches the plans section when the smart destination targets an active plan', function () {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create();

        ReadingPlanSubscription::factory()
            ->for($user)
            ->for($plan)
            ->create();

        $response = ($this->getDashboard)($user);

        $response->assertSuccessful();

        $content = $response->getContent();
        $plansLink = substr($content, strpos($content, 'id="desktop-plans-link"'));

        expect($plansLink)
            ->toContain('hx-get="'.route('plans.today', $plan).'"')
            ->toContain("isSidebarPathActive('/plans', true)");
    });
});

describe('Accessibility Features', function () {
    it('includes :dataset accessibility markup', function (string $markup) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();
        $response->assertSee($markup, false);
    })->with([
        'screen-reader-only labels' => 'sr-only',
        'aria-hidden decorative icons' => 'aria-hidden="true"',
    ]);

    it('marks the active desktop navigation link accessibly and visually', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        expect($response->getContent())
            ->toContain("x-bind:aria-current=\"isSidebarPathActive('/dashboard', false) ? 'page' : null\"")
            ->toContain("'bg-primary-50 text-primary-700 dark:bg-gray-700 dark:text-white': isSidebarPathActive('/dashboard', false)")
            ->toContain('data-sidebar-nav-link')
            ->not->toContain("'!border-primary-600': isSidebarPathActive('/dashboard', false)");
    });

    it('renders the sidebar toggle in a quiet utility row with consistent spacing', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        $content = $response->getContent();
        $control = substr(
            $content,
            strpos($content, 'data-sidebar-control'),
            strpos($content, 'id="desktop-sidebar-navigation"') - strpos($content, 'data-sidebar-control'),
        );

        expect($content)
            ->toContain('data-sidebar-control')
            ->toContain('mb-2 flex h-10')
            ->toContain("sidebarCollapsed ? 'justify-center' : 'justify-end'")
            ->toContain('bg-transparent')
            ->not->toContain('absolute top-2 z-10')
            ->not->toContain('shadow-xs')
            ->not->toContain('border-b border-gray-200 bg-gray-50/80');

        expect($control)
            ->toContain('focus-visible:ring-2')
            ->toContain('focus-visible:ring-primary-500')
            ->not->toContain('focus:ring-2')
            ->not->toContain('focus:ring-primary-500');

        expect($control)->not->toContain('border border-gray-200');
    });

    it('includes aria-expanded attribute on dropdown button', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        $response->assertSee('aria-expanded', false);
    });

    it('includes accessible label for dropdown button', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        $response->assertSee('sr-only', false);
        $response->assertSee('Open user menu', false);
    });
});

describe('Navigation URL Management', function () {
    it('uses named routes for navigation links', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        $response->assertSee(route('dashboard'));
        $response->assertSee(route('logs.create'));
        $response->assertSee(route('logs.index'));
    });

    it('preserves HTMX wiring on collapsible desktop sidebar links', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();

        $content = $response->getContent();
        $sidebar = substr($content, strpos($content, 'id="desktop-sidebar-navigation"'));

        expect($sidebar)
            ->toContain('hx-get="'.route('dashboard').'"')
            ->toContain('hx-get="'.route('logs.create').'"')
            ->toContain('hx-target="#page-container"')
            ->toContain('hx-swap="innerHTML"')
            ->toContain('hx-push-url="true"')
            ->toContain('title="Dashboard"')
            ->toContain('title="Log Reading"');
    });

    it('includes hx-push-url attribute for browser history', function () {
        $response = ($this->getDashboard)();

        $response->assertSuccessful();
        $response->assertSee('hx-push-url="true"', false);
    });
});

describe('Navigation Component Integration', function () {
    it('renders :dataset integration markup', function (array $expectedMarkup) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();

        foreach ($expectedMarkup as $markup) {
            $response->assertSee($markup, false);
        }
    })->with([
        'page container' => [['id="page-container"']],
        'authenticated navigation components' => [['<nav', '<aside', 'rounded-full bottom-mobile-nav-safe']],
        'browser navigation support' => [['htmx:historyRestore', 'HTMX History Configuration']],
    ]);
});

describe('Brand Styling', function () {
    it('uses brand styling for the :dataset', function (array $expectedClasses) {
        $response = ($this->getDashboard)();
        $response->assertSuccessful();

        foreach ($expectedClasses as $class) {
            $response->assertSee($class, false);
        }
    })->with([
        'profile avatar' => [['bg-primary-500', 'focus:ring-primary-300']],
        'Log Reading button' => [['bg-accent-500', 'hover:bg-accent-600']],
        'sidebar hover states' => [['hover:bg-primary-50']],
    ]);
});
