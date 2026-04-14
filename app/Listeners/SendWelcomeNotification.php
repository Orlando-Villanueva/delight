<?php

namespace App\Listeners;

use App\Notifications\WelcomeNotification;
use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;

class SendWelcomeNotification
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $this->emailService->sendWithErrorHandling(function () use ($event): void {
            $event->user->notify(new WelcomeNotification);
        }, 'welcome-notification');
    }
}
