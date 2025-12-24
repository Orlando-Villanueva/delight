<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;

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

        $announcements = \App\Models\Announcement::visible()
            ->latest('starts_at')
            ->take(10)
            ->get();

        // Pass unread IDs to view for bolding
        $unreadIds = $unread->pluck('id')->toArray();

        return view('partials.notification-bell-dropdown', compact('announcements', 'unreadIds'));
    }

    public function markAsRead(\App\Models\Announcement $announcement)
    {
        auth()->user()->announcements()->syncWithoutDetaching([
            $announcement->id => ['read_at' => now()],
        ]);

        return response()->noContent();
    }
}
