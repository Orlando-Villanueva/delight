<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\EmailService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF middleware for all tests in this class
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_user_can_register_with_valid_credentials()
    {
        Notification::fake();

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('ValidPass123!', $user->password));
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_user_registration_succeeds_when_welcome_email_delivery_fails()
    {
        $emailService = $this->mock(EmailService::class);
        $emailService->shouldReceive('sendWithErrorHandling')
            ->once()
            ->andReturn(false);

        $this->app->instance(EmailService::class, $emailService);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_user_registration_requires_valid_email()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'invalid-email']);
    }

    public function test_user_registration_enforces_password_requirements()
    {
        // Test with short password (less than 8 characters)
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Pass12!',
            'password_confirmation' => 'Pass12!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }

    public function test_user_registration_requires_password_with_numbers()
    {
        // Test with password without numbers
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPassword!',
            'password_confirmation' => 'ValidPassword!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }

    public function test_user_registration_prevents_duplicate_emails()
    {
        // Create initial user
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'Jane Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('email');

        // Ensure only one user exists with this email
        $this->assertEquals(1, User::where('email', 'john@example.com')->count());
    }

    /**
     * Ensure password confirmation is required for registration.
     */
    public function test_user_registration_requires_password_confirmation()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'WrongPass123!',
        ];

        $response = $this->post('/register', $userData);
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('ValidPass123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('ValidPass123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_user_model_relationships_exist()
    {
        $user = User::factory()->create();

        // Test that relationships are defined (will return empty collections initially)
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->readingLogs);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->bookProgress);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->recentReadingLogs);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->completedBooks);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->booksInProgress);
    }

    public function test_user_data_fields_are_properly_configured()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
        ]);

        // Test fillable fields
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);

        // Test hidden fields
        $userArray = $user->toArray();
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);

        // Test casts
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);

        // Test password is hashed
        $this->assertTrue(Hash::check('ValidPass123!', $user->password));
    }

    public function test_session_management_works_correctly()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('ValidPass123!'),
        ]);

        // Login with remember me
        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'ValidPass123!',
            'remember' => true,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Check that remember token is set
        $user->refresh();
        $this->assertNotNull($user->remember_token);
    }
}
