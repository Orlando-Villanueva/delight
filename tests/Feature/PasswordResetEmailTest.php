<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_email_can_be_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');
    }

    public function test_password_reset_email_uses_custom_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        Notification::assertSentTo(
            $user,
            CustomResetPasswordNotification::class
        );
    }

    public function test_password_reset_request_succeeds_when_email_delivery_fails(): void
    {
        $this->mock(EmailService::class)
            ->shouldReceive('sendWithErrorHandling')
            ->once()
            ->andReturn(false);

        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');
    }

    public function test_password_reset_email_contains_correct_branding(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create a notification instance to test URL generation
        $token = Password::createToken($user);
        $notification = new CustomResetPasswordNotification($token);

        // Test that the reset URL is generated correctly
        $resetUrl = $notification->resetUrl($user);
        $this->assertStringContainsString('reset-password', $resetUrl);
        $this->assertStringContainsString($token, $resetUrl);
        $this->assertStringContainsString(urlencode($user->email), $resetUrl);
    }

    public function test_password_reset_form_validation(): void
    {
        // Test empty email
        $response = $this->post('/forgot-password', [
            'email' => '',
        ]);

        $response->assertSessionHasErrors(['email']);

        // Test invalid email format
        $response = $this->post('/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors(['email']);

        // Test non-existent email
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_link_works(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->get("/reset-password/{$token}?email=test@example.com");

        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
        $response->assertViewHas('request');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'VeryUniqueTestPassword2024!@#',
            'password_confirmation' => 'VeryUniqueTestPassword2024!@#',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        // Verify user can login with new password
        $this->assertTrue(auth()->attempt([
            'email' => 'test@example.com',
            'password' => 'VeryUniqueTestPassword2024!@#',
        ]));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'VeryUniqueTestPassword2024!@#',
            'password_confirmation' => 'VeryUniqueTestPassword2024!@#',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_email_service_handles_errors_gracefully(): void
    {
        $emailService = app(\App\Services\EmailService::class);

        // Test with a callback that throws an exception
        $result = $emailService->sendWithErrorHandling(function () {
            throw new \Exception('Test email error');
        }, 'test-context');

        $this->assertFalse($result);
    }

    public function test_email_configuration_status(): void
    {
        $emailService = app(\App\Services\EmailService::class);
        $status = $emailService->getConfigurationStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('mailer', $status);
        $this->assertArrayHasKey('from_address', $status);
        $this->assertArrayHasKey('from_name', $status);
    }
}
