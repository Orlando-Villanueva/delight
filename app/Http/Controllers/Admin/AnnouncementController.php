<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest()->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request)
    {
        // Default to now() if starts_at is empty (meaning "Publish Now")
        if (! $request->filled('starts_at')) {
            $request->merge(['starts_at' => now()]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:info,warning,success',
            'starts_at' => 'required|date', // Now required since we default it
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

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
}
