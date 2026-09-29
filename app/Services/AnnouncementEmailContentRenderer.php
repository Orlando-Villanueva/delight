<?php

namespace App\Services;

use App\Models\Announcement;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Str;

class AnnouncementEmailContentRenderer
{
    public function render(Announcement $announcement): string
    {
        $html = Str::markdown($announcement->content);
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $articleUrl = new Uri(route('announcements.show', $announcement->slug));

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
                continue;
            }

            $anchor->setAttribute('href', (string) UriResolver::resolve($articleUrl, new Uri($href)));
        }

        return $document->body->innerHTML;
    }
}
