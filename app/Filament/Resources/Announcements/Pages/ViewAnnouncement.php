<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label($this->getRecord()->isPublished() ? 'View announcement' : 'Preview announcement')
                ->url($this->getRecord()->isPublished()
                    ? route('announcements.show', $this->getRecord()->slug)
                    : route('admin.announcements.preview', $this->getRecord()->slug)),
        ];
    }
}
