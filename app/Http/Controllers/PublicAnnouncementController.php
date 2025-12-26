<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class PublicAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::visible()
            ->orderBy('starts_at', 'desc')
            ->paginate(10);

        return view('announcements.index', compact('announcements'));
    }

    public function show($slug)
    {
        // Use published() so expired announcements remain reachable by direct URL
        // even though they are hidden from in-app lists.
        $announcement = Announcement::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // access control for "Read" marking
        if (auth()->check()) {
            // Use attach instead of syncWithoutDetaching to ensure created_at is set if we want,
            // but syncWithoutDetaching is safer to prevent duplicates.
            auth()->user()->announcements()->syncWithoutDetaching([
                $announcement->id => ['read_at' => now()],
            ]);
        }

        return view('announcements.show', compact('announcement'));
    }
}
