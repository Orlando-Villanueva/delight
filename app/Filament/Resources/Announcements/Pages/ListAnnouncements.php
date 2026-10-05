<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    public function mount(): void
    {
        parent::mount();

        if (session()->has('success')) {
            Notification::make()->title((string) session()->pull('success'))->success()->send();
        }
    }

    protected function authorizeAccess(): void
    {
        abort_unless(AnnouncementResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newAnnouncement')
                ->label('New announcement')
                ->url(route('admin.announcements.create')),
        ];
    }
}
