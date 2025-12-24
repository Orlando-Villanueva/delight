<?php

namespace App\Http\Controllers;

class PublicAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = \App\Models\Announcement::visible()
            ->orderBy('starts_at', 'desc')
            ->paginate(10);

        return view('announcements.index', compact('announcements'));
    }

    public function show($slug)
    {
        $announcement = \App\Models\Announcement::published()
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
