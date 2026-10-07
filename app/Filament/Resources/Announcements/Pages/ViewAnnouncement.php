<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Services\AnnouncementEmailDeliveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryFailedRecipients')
                ->label('Retry failed recipients')
                ->color('warning')
                ->authorize(fn (): bool => AnnouncementResource::canViewAny())
                ->visible(fn (AnnouncementEmailDeliveryService $service): bool => $service->countRetryableFailures($this->getRecord()) > 0)
                ->requiresConfirmation()
                ->modalHeading('Retry failed recipients')
                ->modalDescription(fn (AnnouncementEmailDeliveryService $service): string => sprintf(
                    'Retry failed recipients for “%s”? Eligible recipients: %d. The background worker will process these retries. Submitted, skipped and uncertain recipients are excluded. This does not confirm inbox delivery.',
                    $this->getRecord()->title,
                    $service->countRetryableFailures($this->getRecord()),
                ))
                ->modalSubmitActionLabel('Retry failed recipients')
                ->action(function (AnnouncementEmailDeliveryService $service): void {
                    abort_unless(AnnouncementResource::canViewAny(), 403);

                    $count = $service->retryFailedForAnnouncement($this->getRecord());
                    $this->getRecord()->refresh();
                    $this->dispatch('announcement-deliveries-retried', announcementId: $this->getRecord()->id);

                    $notification = Notification::make()->title($count > 0
                        ? sprintf('%d failed recipient%s marked for retry.', $count, $count === 1 ? '' : 's')
                        : 'No eligible failed recipients remain.');

                    if ($count > 0) {
                        $notification->success();
                    } else {
                        $notification->warning();
                    }

                    $notification->send();
                }),
            Action::make('editDraft')
                ->label('Edit draft')
                ->visible(fn (): bool => AnnouncementResource::canEdit($this->getRecord()))
                ->url(fn (): string => AnnouncementResource::getUrl('edit', ['record' => $this->getRecord()], panel: 'admin')),
            Action::make('preview')->label($this->getRecord()->isPublished() ? 'View announcement' : 'Preview announcement')
                ->url($this->getRecord()->isPublished()
                    ? route('announcements.show', $this->getRecord()->slug)
                    : route('admin.announcements.preview', $this->getRecord()->slug)),
        ];
    }
}
