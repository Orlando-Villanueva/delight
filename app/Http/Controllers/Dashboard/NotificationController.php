<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index()
    {
        // Get unread first, then read, limit 10
        // We can use the logic: unread + read (limit 15 - unread.count)

        $unread = auth()->user()->unreadAnnouncements()->get();
        // Since unreadAnnouncements returns a Builder, we get Collection.

        // We might want history too.
        // Let's simplify: Get the last 15 active announcements,
        // and in the view we check if they are in "unread" list.

        $announcements = Announcement::visible()
            ->latest('starts_at')
            ->take(10)
            ->get();

        // Pass unread IDs to view for bolding
        $unreadIds = $unread->pluck('id')->toArray();

        return view('partials.notification-bell-dropdown', compact('announcements', 'unreadIds'));
    }

    public function markAsRead(Announcement $announcement): Response
    {
        $visibleAnnouncement = Announcement::visible()->findOrFail($announcement->getKey());

        auth()->user()->announcements()->syncWithoutDetaching([
            $visibleAnnouncement->id => ['read_at' => now()],
        ]);

        return response()->noContent();
    }
}
