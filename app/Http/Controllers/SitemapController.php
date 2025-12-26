<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Landing page
        $sitemap .= '<url>';
        $sitemap .= '<loc>'.config('app.url').'</loc>';
        $sitemap .= '<lastmod>'.now()->toISOString().'</lastmod>';
        $sitemap .= '<changefreq>weekly</changefreq>';
        $sitemap .= '<priority>1.0</priority>';
        $sitemap .= '</url>';

        // Legal pages
        $sitemap .= '<url>';
        $sitemap .= '<loc>'.config('app.url').'/privacy-policy</loc>';
        $sitemap .= '<lastmod>'.now()->toISOString().'</lastmod>';
        $sitemap .= '<changefreq>monthly</changefreq>';
        $sitemap .= '<priority>0.3</priority>';
        $sitemap .= '</url>';

        $sitemap .= '<url>';
        $sitemap .= '<loc>'.config('app.url').'/terms-of-service</loc>';
        $sitemap .= '<lastmod>'.now()->toISOString().'</lastmod>';
        $sitemap .= '<changefreq>monthly</changefreq>';
        $sitemap .= '<priority>0.3</priority>';
        $sitemap .= '</url>';

        // Announcements
        $announcements = \App\Models\Announcement::active()->get();
        foreach ($announcements as $announcement) {
            $sitemap .= '<url>';
            $sitemap .= '<loc>'.route('announcements.show', $announcement->slug).'</loc>';
            $sitemap .= '<lastmod>'.$announcement->updated_at->toIso8601String().'</lastmod>';
            $sitemap .= '<changefreq>monthly</changefreq>';
            $sitemap .= '<priority>0.8</priority>'; // High priority for news
            $sitemap .= '</url>';
        }

        $sitemap .= '</urlset>';

        return response($sitemap, 200, ['Content-Type' => 'application/xml']);
    }
}
