<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Services\AnnouncementService;
use App\Services\AnnouncementValidator;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'New announcement draft';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $validated = app(AnnouncementValidator::class)->validate($data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])
                ->all());
        }

        return app(AnnouncementService::class)->createDraft($validated);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save draft');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Announcement draft created.';
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.announcements.preview', $this->getRecord()->slug);
    }
}
