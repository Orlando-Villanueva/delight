<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = \App\Models\Announcement::latest()->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(\Illuminate\Http\Request $request)
    {
        // Default to now() if starts_at is empty (meaning "Publish Now")
        if (!$request->filled('starts_at')) {
            $request->merge(['starts_at' => now()]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:info,warning,success',
            'starts_at' => 'required|date', // Now required since we default it
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['title']) . '-' . now()->timestamp;

        $announcement = \App\Models\Announcement::create($validated);

        // TODO: Handle Email Notification here if we add that checkbox later

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement created successfully.');
    }
}
