<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    public function test_password_reset_email_template_renders(): void
    {
        $resetUrl = 'https://example.com/reset-password/test-token?email=test@example.com';

        $mailable = new PasswordResetMail($resetUrl);

        $rendered = $mailable->render();

        // Test that the email contains expected content
        $this->assertStringContainsString('Delight', $rendered);
        $this->assertStringContainsString('Reset Your Password', $rendered);
        $this->assertStringContainsString('Reset My Password', $rendered);
        $this->assertStringContainsString($resetUrl, $rendered);
        $this->assertStringContainsString('Security Notice', $rendered);
        $this->assertStringContainsString('Send us Feedback', $rendered);

        // Test that the email has proper HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $rendered);
        $this->assertStringContainsString('<html lang="en">', $rendered);
        $this->assertStringContainsString('</html>', $rendered);
    }

    public function test_password_reset_email_has_proper_subject(): void
    {
        $resetUrl = 'https://example.com/reset-password/test-token?email=test@example.com';

        $mailable = new PasswordResetMail($resetUrl);

        $envelope = $mailable->envelope();

        $this->assertEquals('Reset Your Password - Delight', $envelope->subject);
    }

    public function test_password_reset_email_uses_correct_view(): void
    {
        $resetUrl = 'https://example.com/reset-password/test-token?email=test@example.com';

        $mailable = new PasswordResetMail($resetUrl);

        $content = $mailable->content();

        $this->assertEquals('emails.password-reset', $content->view);
        $this->assertArrayHasKey('resetUrl', $content->with);
        $this->assertEquals($resetUrl, $content->with['resetUrl']);
    }
}
