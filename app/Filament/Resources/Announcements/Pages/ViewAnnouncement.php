<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementEmailDeliveryService;
use App\Services\AnnouncementService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    /** @var array<string, mixed> */
    #[Locked]
    public array $emailAuthorizationPreview = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publishDraft')
                ->label(fn (): string => $this->getRecord()->starts_at?->isFuture() ? 'Schedule announcement' : 'Publish announcement')
                ->authorize(fn (): bool => AnnouncementResource::canViewAny())
                ->visible(fn (): bool => AnnouncementResource::canEdit($this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Publish or schedule announcement')
                ->modalDescription(fn (): string => sprintf(
                    'Publish “%s” in-app %s? Expiry: %s. This uses the saved draft and does not authorize email. Publication ends draft editing.',
                    $this->getRecord()->title,
                    $this->getRecord()->starts_at?->isFuture()
                        ? 'at '.$this->getRecord()->starts_at->format('M j, Y H:i:s T')
                        : 'now',
                    $this->getRecord()->ends_at?->format('M j, Y H:i:s T') ?? 'none',
                ))
                ->modalSubmitActionLabel('Confirm publication')
                ->action(function (Action $action, AnnouncementService $service): void {
                    abort_unless(AnnouncementResource::canViewAny(), 403);

                    try {
                        DB::transaction(function () use ($service): void {
                            $current = Announcement::query()->lockForUpdate()->findOrFail($this->getRecord()->id);

                            if (! $current->is_draft) {
                                throw ValidationException::withMessages(['draft' => 'Only an existing draft announcement can be published.']);
                            }

                            $startsAt = $current->starts_at?->isFuture() ? $current->starts_at : now();

                            if ($current->ends_at?->lte($startsAt)) {
                                throw ValidationException::withMessages(['ends_at' => 'The expiry time must be after the publication time.']);
                            }

                            $service->publishDraft($current, $startsAt);
                        });
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Announcement could not be published')
                            ->body(collect($exception->errors())->flatten()->implode("\n"))
                            ->persistent()->send();
                        $action->halt();
                    }

                    $this->getRecord()->refresh();

                    Notification::make()->success()
                        ->title($this->getRecord()->starts_at->isFuture() ? 'Announcement scheduled.' : 'Announcement published.')
                        ->body('Email has not been authorized.')
                        ->send();
                }),
            Action::make('authorizeEmail')
                ->label('Authorize email')
                ->authorize(fn (): bool => AnnouncementResource::canViewAny())
                ->visible(fn (): bool => ! $this->getRecord()->is_draft && $this->getRecord()->email_broadcast_authorized_at === null)
                ->requiresConfirmation()
                ->mountUsing(function (Action $action, AnnouncementService $service): void {
                    abort_unless(AnnouncementResource::canViewAny(), 403);

                    try {
                        $this->emailAuthorizationPreview = $service->previewEmailAuthorization($this->getRecord());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Email could not be authorized')
                            ->body(collect($exception->errors())->flatten()->implode("\n"))
                            ->persistent()->send();
                        $action->cancel();
                    }
                })
                ->modalHeading('Authorize announcement email')
                ->modalDescription('Review the audience before authorizing email.')
                ->schema([
                    TextEntry::make('announcement_title')->label('Announcement')
                        ->state(fn (): string => $this->emailAuthorizationPreview['title'] ?? ''),
                    TextEntry::make('publication_time')->label('Publication time')
                        ->state(fn (): ?string => $this->emailAuthorizationPreview['starts_at'] ?? null)
                        ->dateTime('M j, Y H:i:s T')
                        ->helperText('Only accounts created by this time are eligible.'),
                    Grid::make(2)->schema([
                        TextEntry::make('eligible_recipients')->label('Estimated eligible')
                            ->state(fn (): int => $this->emailAuthorizationPreview['eligible_recipients'] ?? 0),
                        TextEntry::make('excluded_recipients')->label('Estimated excluded')
                            ->state(fn (): int => $this->emailAuthorizationPreview['excluded_recipients'] ?? 0),
                    ]),
                    Text::make('Estimates may change. The worker finalizes the audience when processing starts.'),
                    Text::make('Email processing can begin once publication is due. Authorization does not send immediately or confirm inbox delivery.'),
                ])
                ->modalSubmitActionLabel('Authorize email')
                ->action(function (Action $action, AnnouncementService $service): void {
                    abort_unless(AnnouncementResource::canViewAny(), 403);

                    try {
                        $authorized = $service->authorizeEmail($this->getRecord());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Email could not be authorized')
                            ->body(collect($exception->errors())->flatten()->implode("\n"))
                            ->persistent()->send();
                        $action->halt();
                    }

                    Notification::make()->success()
                        ->title($authorized ? 'Announcement email authorized.' : 'Announcement email already authorized.')
                        ->body('The background worker will process email once publication is due. This does not confirm inbox delivery.')
                        ->send();
                }),
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
