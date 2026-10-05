<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementEmailDeliveryService;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementEmailDeliveryService $emailDeliveryService,
        private AnnouncementService $announcementService,
    ) {}

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->announcementService->createPublishedOrScheduled($request->validated());

        $message = $announcement->starts_at?->isFuture()
            ? 'Announcement scheduled.'
            : 'Announcement published.';

        return redirect()->route('admin.announcements.index')
            ->with('success', $message);
    }

    public function edit(Announcement $announcement): View
    {
        abort_unless($announcement->is_draft, 404);

        return view('admin.announcements.create', compact('announcement'));
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->is_draft, 404);

        $announcement = $this->announcementService->updateDraft($announcement, $request->validated());

        return redirect()->route('admin.announcements.preview', ['announcement' => $announcement->slug])
            ->with('success', 'Announcement draft updated.');
    }

    public function previewMarkdown(Request $request)
    {
        $content = (string) $request->input('content', '');
        $trimmedContent = trim($content);
        $previewHtml = $trimmedContent !== '' ? Str::markdown($content) : '';

        return response()->htmx('admin.announcements.create', 'announcement-preview', [
            'previewHtml' => $previewHtml,
            'previewIsEmpty' => $trimmedContent === '',
        ]);
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
