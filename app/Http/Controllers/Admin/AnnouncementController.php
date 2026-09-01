<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementEmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementEmailDeliveryService $emailDeliveryService
    ) {}

    public function index()
    {
        $announcements = Announcement::query()
            ->with('latestFailedEmailDelivery')
            ->withCount([
                'emailDeliveries',
                'emailDeliveries as email_sent_count' => fn ($query) => $query->whereNotNull('sent_at'),
                'emailDeliveries as email_skipped_count' => fn ($query) => $query->whereNotNull('skipped_at'),
                'emailDeliveries as email_failed_count' => fn ($query) => $query->whereNotNull('failed_at'),
                'emailDeliveries as email_uncertain_count' => fn ($query) => $query->whereNotNull('uncertain_at'),
            ])
            ->latest()
            ->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['slug'] = Str::slug($validated['title']).'-'.now()->timestamp;

        Announcement::create($validated);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement created successfully.');
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
