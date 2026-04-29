<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
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

    public function store(StoreAnnouncementRequest $request)
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
}
