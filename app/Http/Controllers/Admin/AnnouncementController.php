<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AnnouncementEmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementEmailDeliveryService $emailDeliveryService,
    ) {}

    public function create(): RedirectResponse
    {
        return redirect(AnnouncementResource::getUrl('create', panel: 'admin'));
    }

    public function edit(Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->is_draft, 404);

        return redirect(AnnouncementResource::getUrl('edit', ['record' => $announcement], panel: 'admin'));
    }

    public function preview(Announcement $announcement): View|RedirectResponse
    {
        if ($announcement->isPublished()) {
            return redirect()->route('announcements.show', ['slug' => $announcement->slug]);
        }

        return view('announcements.show', [
            'announcement' => $announcement,
            'isPreview' => true,
        ]);
    }

    public function retryFailedEmailDeliveries(Announcement $announcement): RedirectResponse
    {
        $retriedCount = $this->emailDeliveryService->retryFailedForAnnouncement($announcement);

        $message = $retriedCount === 1
            ? 'One failed announcement email will be retried.'
            : "{$retriedCount} failed announcement emails will be retried.";

        return redirect()->route('admin.announcements.index')->with('success', $message);
    }
}
