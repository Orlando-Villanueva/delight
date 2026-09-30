<?php

namespace App\Services;

use App\Models\Announcement;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            try {
                $anchor->setAttribute('href', $this->resolveUrl($anchor->getAttribute('href'), $announcement));
            } catch (InvalidArgumentException) {
                $anchor->removeAttribute('href');
            }
        }

        foreach ($document->querySelectorAll('picture source') as $source) {
            $source->remove();
        }

        foreach ($document->querySelectorAll('img') as $image) {
            $image->removeAttribute('srcset');
            $image->removeAttribute('sizes');
            if ($image->hasAttribute('src')) {
                try {
                    $image->setAttribute('src', $this->resolveUrl($image->getAttribute('src'), $announcement));
                } catch (InvalidArgumentException) {
                    $image->replaceWith($document->createTextNode($image->getAttribute('alt') ?? ''));

                    continue;
                }
            }

            $authoredStyle = $image->getAttribute('style') ?? '';
            $width = 'auto';
            $attributeWidth = $image->getAttribute('width') ?? '';

            if (preg_match('/^\d+(?:\.\d+)?$/', $attributeWidth) && (float) $attributeWidth > 0) {
                $width = $attributeWidth.'px';
            }

            if (preg_match_all('/(?:^|;)\s*width\s*:\s*(\d+(?:\.\d+)?(?:px|%))\s*(?:!important)?\s*(?=;|$)/i', $authoredStyle, $matches)) {
                $width = strtolower(end($matches[1]));
            }

            $image->removeAttribute('width');
            $image->removeAttribute('height');
            $style = preg_replace(
                '/(?:^|;)\s*(?:(?:min|max)-)?(?:width|height)\s*:[^;]*(?=;|$)/i',
                ';',
                $authoredStyle,
            );
            $style = trim($style, " \t\n\r\0\x0B;");
            $image->setAttribute('style', ($style !== '' ? $style.'; ' : '').'max-width: 100%; width: '.$width.'; height: auto;');
        }

        return $document->body->innerHTML;
    }

    public function resolveUrl(string $reference, Announcement $announcement): string
    {
        $reference = trim($reference);

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $reference)) {
            return $reference;
        }

        return (string) UriResolver::resolve(
            new Uri(route('announcements.show', $announcement->slug)),
            new Uri($reference),
        );
    }
}
