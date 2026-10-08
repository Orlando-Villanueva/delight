<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\Actions\SendTestEmailAction;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\AnnouncementValidator;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditAnnouncement extends EditRecord
{
    #[Locked]
    public ?string $loadedDraftFingerprint = null;

    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'Edit announcement draft';

    protected function afterFill(): void
    {
        $this->loadedDraftFingerprint = $this->draftFingerprint($this->getRecord());
    }

    private function draftFingerprint(Model $record): string
    {
        return hash('sha256', json_encode($record->only([
            'title', 'slug', 'content', 'hero_image_path', 'social_image_path', 'starts_at', 'ends_at',
        ]), JSON_THROW_ON_ERROR));
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            SendTestEmailAction::make('sendTestEmail'),
            Action::make('preview')->label('Preview saved draft')
                ->url(fn (): string => route('admin.announcements.preview', $this->getRecord()->slug))
                ->openUrlInNewTab(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = Announcement::query()->lockForUpdate()->findOrFail($record->getKey());
            abort_unless(AnnouncementResource::canEdit($current), 403);

            if ($this->draftFingerprint($current) !== $this->loadedDraftFingerprint) {
                throw ValidationException::withMessages([
                    'data.title' => 'This draft changed after you opened it. Copy your unsaved edits before reloading the page.',
                ]);
            }

            try {
                $validated = app(AnnouncementValidator::class)->validate($data, $current);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])
                    ->all());
            }

            app(AnnouncementService::class)->updateDraft($current, $validated);

            $record->refresh();
            $this->loadedDraftFingerprint = $this->draftFingerprint($record);
            $this->refreshFormData(['slug', 'starts_at']);

            return $record;
        });
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save draft');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Announcement draft updated.';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
