<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public ?string $capacityDeferral = null;

    /**
     * Send an email with error handling and logging.
     */
    public function sendWithErrorHandling(callable $mailCallback, string $context = 'email'): bool
    {
        $this->capacityDeferral = null;

        if ($context === 'onboarding-reminder' || str_starts_with($context, 'reading-log-reengagement-')) {
            $this->capacityDeferral = app(AutomatedEmailCapacityService::class)->reserve($context);

            if ($this->capacityDeferral !== null) {
                Log::info('Automated email deferred for capacity.', ['context' => $context, 'reason' => $this->capacityDeferral]);

                return false;
            }
        }

        try {
            $mailCallback();

            Log::info('Email sent successfully', [
                'context' => $context,
                'timestamp' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Email delivery failed', [
                'context' => $context,
                'error' => $e->getMessage(),
                'timestamp' => now(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Test email configuration by sending a test email.
     */
    public function testEmailConfiguration(): array
    {
        try {
            // Test basic mail configuration
            $mailer = config('mail.default');
            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name');

            if (empty($fromAddress) || empty($fromName)) {
                return [
                    'success' => false,
                    'message' => 'Email configuration incomplete: missing from address or name',
                ];
            }

            // For local development with Mailpit, we can't actually send
            // but we can verify the configuration is set up correctly
            if ($mailer === 'smtp' && config('mail.mailers.smtp.host') === 'localhost') {
                return [
                    'success' => true,
                    'message' => 'Email configuration appears correct for local development (Mailpit)',
                    'mailer' => $mailer,
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                ];
            }

            // For production Mailgun
            if ($mailer === 'mailgun') {
                $domain = config('mail.mailers.mailgun.domain');
                $secret = config('mail.mailers.mailgun.secret');

                if (empty($domain) || empty($secret)) {
                    return [
                        'success' => false,
                        'message' => 'Mailgun configuration incomplete: missing domain or secret',
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Mailgun configuration appears correct',
                    'mailer' => $mailer,
                    'domain' => $domain,
                ];
            }

            return [
                'success' => true,
                'message' => "Email configuration set for {$mailer}",
                'mailer' => $mailer,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email configuration test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get email configuration status for debugging.
     */
    public function getConfigurationStatus(): array
    {
        return [
            'mailer' => config('mail.default'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'mailgun_domain' => ! empty(config('mail.mailers.mailgun.domain')),
            'mailgun_secret' => ! empty(config('mail.mailers.mailgun.secret')),
        ];
    }
}
