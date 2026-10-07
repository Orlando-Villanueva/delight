<?php

namespace App\Filament\Resources\Announcements\Actions;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementTestEmailService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendTestEmailAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Send test email')
            ->icon('heroicon-m-envelope')
            ->authorize(fn (): bool => AnnouncementResource::canViewAny())
            ->visible(fn (Announcement $record): bool => $record->is_draft)
            ->requiresConfirmation()
            ->modalHeading('Send saved draft test email')
            ->modalDescription('Send one test email to the configured admin inbox.')
            ->schema([
                TextEntry::make('recipient')->label('Recipient')->state(fn (): ?string => config('mail.admin_address')),
                TextEntry::make('subject')->label('Subject')->state(fn (Announcement $record): string => '[TEST] '.$record->title),
                Text::make('Uses the saved draft. Save any edits before sending.'),
                Text::make('This does not publish the announcement or authorize a broadcast.'),
            ])
            ->modalSubmitActionLabel('Send test email')
            ->action(function (Action $action, Announcement $record, AnnouncementTestEmailService $service): void {
                abort_unless(AnnouncementResource::canViewAny(), 403);

                try {
                    $recipient = $service->send($record);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Test email could not be sent')
                        ->body(collect($exception->errors())->flatten()->implode("\n"))->persistent()->send();
                    $action->halt();
                } catch (TransportExceptionInterface $exception) {
                    report($exception);
                    Notification::make()->danger()->title('Test email could not be sent')
                        ->body('The mail transport rejected the test email. Check the application logs for details.')
                        ->persistent()->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Test email submitted to the mail transport')
                    ->body('Recipient: '.$recipient.'. Check your inbox to confirm delivery.')->send();
            });
    }
}
