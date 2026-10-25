<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoreFunctionalityValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test user registration flow
     */
    public function test_user_registration_flow(): void
    {
        // Test registration page loads
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Create account');
        $response->assertSee('Start your Bible reading journey today');

        // Test successful registration
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecureTestPassword2024!',
            'password_confirmation' => 'SecureTestPassword2024!',
        ];

        $response = $this->post('/register', $userData);
        $response->assertRedirect('/dashboard');

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Verify user is authenticated
        $this->assertAuthenticated();
    }

    /**
     * Test user login flow
     */
    public function test_user_login_flow(): void
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('SecureTestPassword2024!'),
        ]);

        // Test login page loads
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Welcome back');
        $response->assertSee('Continue your Bible reading journey');

        // Test successful login
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'SecureTestPassword2024!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test dashboard functionality
     */
    public function test_dashboard_functionality(): void
    {
        $user = User::factory()->create();

        // Create some reading logs for testing
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 2,
            'passage_text' => 'Genesis 2',
            'date_read' => today()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertViewHas('page', fn (array $page): bool => ($page['encryptHistory'] ?? false) === true);

        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('summary.totalChapters', 2)
            ->has('summary.daysRead')
            ->has('summary.bookCompletions')
            ->has('summary.averageChaptersPerDay')
            ->has('recentActivity', 2)
            ->where('recentActivity.0.passageText', 'Genesis 1')
            ->where('recentActivity.1.passageText', 'Genesis 2'));

        $this->actingAs($user)->getJson(route('dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('summary.totalChapters', 2)
            ->assertJsonPath('recentActivity.0.passageText', 'Genesis 1')
            ->assertJsonMissingPath('stats')
            ->assertJsonMissingPath('user.password');
    }

    /**
     * Test reading log creation functionality
     */
    public function test_reading_log_creation(): void
    {
        $user = User::factory()->create();

        // Test reading log form loads
        $response = $this->actingAs($user)->get('/logs/create');
        $response->assertStatus(200);
        $response->assertSee('Log Reading');

        // Test successful reading log creation
        $readingData = [
            'book_id' => 1, // Genesis
            'start_chapter' => '1',
            'date_read' => today()->toDateString(),
            'notes_text' => 'Great chapter about creation',
        ];

        $response = $this->actingAs($user)->post('/logs', $readingData);
        $response->assertStatus(200);
        $response->assertSee('Reading Logged Successfully!');

        // Verify reading log was created
        $this->assertDatabaseHas('reading_logs', [
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'notes_text' => 'Great chapter about creation',
        ]);

        // Verify the date separately (since it might include time)
        $readingLog = ReadingLog::where('user_id', $user->id)
            ->where('book_id', 1)
            ->where('chapter', 1)
            ->first();
        $this->assertNotNull($readingLog);
        $this->assertEquals(today()->toDateString(), $readingLog->date_read->toDateString());
    }

    /**
     * Test reading history page
     */
    public function test_reading_history_functionality(): void
    {
        $user = User::factory()->create();

        // Create reading logs for different dates
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'passage_text' => 'Genesis 1',
            'date_read' => today(),
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 2,
            'chapter' => 1,
            'passage_text' => 'Exodus 1',
            'date_read' => today()->subDays(2),
        ]);

        // Test the main logs page loads
        $response = $this->actingAs($user)->get('/logs');
        $response->assertStatus(200);

        // Test the logs page contains the reading logs
        $response->assertSee('Genesis 1');
        $response->assertSee('Exodus 1');
    }

    /**
     * Test mobile responsiveness by checking viewport meta tag and responsive classes
     */
    public function test_mobile_responsiveness_elements(): void
    {
        // Test authenticated pages first
        $user = User::factory()->create();

        // Test dashboard mobile responsiveness
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Check for viewport meta tag
        $response->assertSee('name="viewport"', false);
        $response->assertSee('width=device-width', false);

        // The Dashboard grid is client-rendered; this response provides its mobile viewport and Inertia mount.
        $response->assertSee('id="app"', false);
        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false));

        // Test guest pages (logout first to clear authentication)
        auth()->logout();

        // Test login page mobile responsiveness (as guest)
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('px-4 sm:px-6 lg:px-8', false);
        $response->assertSee('max-w-md', false);

        // Test registration page mobile responsiveness (as guest)
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('px-4 sm:px-6 lg:px-8', false);
        $response->assertSee('max-w-md', false);
    }

    /**
     * Test validation errors are handled properly
     */
    public function test_validation_error_handling(): void
    {
        $user = User::factory()->create();

        // Test invalid reading log data
        $invalidData = [
            'book_id' => 999, // Invalid book ID
            'start_chapter' => 'invalid',
            'date_read' => 'invalid-date',
        ];

        $response = $this->actingAs($user)->post('/logs', $invalidData);
        $response->assertRedirect(route('logs.create'))->assertSessionHasErrors(['book_id', 'start_chapter', 'date_read']);
    }

    /**
     * Test current week reading day statistics in dashboard
     */
    public function test_current_week_reading_day_statistics_integration(): void
    {
        $user = User::factory()->create();

        // Create reading logs for current week (3 different days)
        $weekStart = now()->startOfWeek(Carbon::SUNDAY);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 1,
            'date_read' => $weekStart, // Sunday
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 2,
            'date_read' => $weekStart->copy()->addDays(2), // Tuesday
        ]);

        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1,
            'chapter' => 3,
            'date_read' => $weekStart->copy()->addDays(4), // Thursday
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->has('summary')
            ->missing('weeklyGoal'));
    }

    /**
     * Test current week reading day counting
     */
    public function test_current_week_reading_day_counting(): void
    {
        $user = User::factory()->create();

        // Create reading logs for 4 different days this week (goal achieved)
        $weekStart = now()->startOfWeek(Carbon::SUNDAY);

        for ($day = 0; $day < 4; $day++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1,
                'chapter' => $day + 1,
                'date_read' => $weekStart->copy()->addDays($day),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->has('summary')
            ->missing('weeklyGoal'));
    }

    /**
     * Test monthly calendar data with chapters read calculation
     */
    public function test_monthly_calendar_chapters_calculation(): void
    {
        $user = User::factory()->create();

        // Create reading logs for different days this month with varying chapter counts
        $today = today();
        $startOfMonth = $today->copy()->startOfMonth();

        // Day 1: Read 1 chapter
        ReadingLog::factory()->create([
            'user_id' => $user->id,
            'book_id' => 1, // Genesis
            'chapter' => 1,
            'date_read' => $startOfMonth,
        ]);

        // Day 2: Read 3 chapters (range)
        for ($chapter = 2; $chapter <= 4; $chapter++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1, // Genesis
                'chapter' => $chapter,
                'date_read' => $startOfMonth->copy()->addDay(),
            ]);
        }

        // Day 5: Read 2 chapters
        for ($chapter = 5; $chapter <= 6; $chapter++) {
            ReadingLog::factory()->create([
                'user_id' => $user->id,
                'book_id' => 1, // Genesis
                'chapter' => $chapter,
                'date_read' => $startOfMonth->copy()->addDays(4),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('calendar.thisMonthReadings', 3)
            ->where('calendar.thisMonthChapters', 6)
            ->has('calendar.days')
            ->has('calendar.successRate'));
    }

    /**
     * Test calendar widget displays correctly with no readings
     */
    public function test_monthly_calendar_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('calendar.thisMonthReadings', 0)
            ->where('calendar.thisMonthChapters', 0)
            ->where('calendar.successRate', 0));
    }
}
