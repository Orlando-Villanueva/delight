<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
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

    public function index(): View
    {
        $announcements = Announcement::query()
            ->with(['latestEmailDelivery', 'latestFailedEmailDelivery'])
            ->withCount([
                'emailDeliveries',
                'emailDeliveries as email_sent_count' => fn ($query) => $query->whereNotNull('sent_at'),
                'emailDeliveries as email_skipped_count' => fn ($query) => $query->whereNotNull('skipped_at'),
                'emailDeliveries as email_failed_count' => fn ($query) => $query->whereNotNull('failed_at'),
                'emailDeliveries as email_uncertain_count' => fn ($query) => $query->whereNotNull('uncertain_at'),
            ])
            ->latest()
            ->paginate(20);

        $hasActiveEmailBroadcasts = $announcements->getCollection()->contains(
            fn (Announcement $announcement): bool => $announcement->email_broadcast_authorized_at !== null
                && $announcement->email_broadcast_completed_at === null
                && $announcement->starts_at?->lte(now())
        );

        return view('admin.announcements.index', compact('announcements', 'hasActiveEmailBroadcasts'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->announcementService->createPublishedOrScheduled($request->validated());

        $message = $announcement->starts_at?->isFuture()
            ? 'Announcement scheduled. Eligible users will be emailed after it is published.'
            : 'Announcement published. Email delivery will begin within five minutes.';

        return redirect()->route('admin.announcements.index')
            ->with('success', $message);
    }

    public function preview(Request $request)
    {
        $content = (string) $request->input('content', '');
        $trimmedContent = trim($content);
        $previewHtml = $trimmedContent !== '' ? Str::markdown($content) : '';

        return response()->htmx('admin.announcements.create', 'announcement-preview', [
            'previewHtml' => $previewHtml,
            'previewIsEmpty' => $trimmedContent === '',
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
