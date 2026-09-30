<?php

namespace App\Services;

use App\Models\Announcement;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AnnouncementEmailLinkValidator
{
    public function __construct(private AnnouncementEmailContentRenderer $contentRenderer) {}

    public function validate(Announcement $announcement): void
    {
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>'.Str::markdown($announcement->content).'</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $errors = [];

        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $reference = trim($anchor->getAttribute('href'));
            $label = trim($anchor->textContent) ?: 'Image or unlabeled link';
            $problem = $this->problem($reference, $announcement);

            if ($problem !== null) {
                $errors[] = "Link \"{$label}\" ({$reference}): {$problem}";
            }
        }

        foreach ($document->querySelectorAll('img') as $image) {
            $reference = trim($image->getAttribute('src') ?? '');
            $label = trim($image->getAttribute('alt') ?? '') ?: 'Unlabeled image';
            $problem = $this->problem($reference, $announcement, isImage: true);

            if ($problem !== null) {
                $errors[] = "Image \"{$label}\" ({$reference}): {$problem}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['content' => $errors]);
        }
    }

    private function problem(string $reference, Announcement $announcement, bool $isImage = false): ?string
    {
        if ($reference === '' || preg_match('/[\x00-\x1f\x7f]/', $reference)) {
            return 'Malformed URL.';
        }

        try {
            $destination = $this->contentRenderer->resolveUrl($reference, $announcement);
            $uri = new Uri($destination);
        } catch (InvalidArgumentException) {
            return 'Malformed URL.';
        }

        $scheme = strtolower($uri->getScheme());

        if ($isImage && ! in_array($scheme, ['http', 'https'], true)) {
            return 'Unsupported image scheme. Use http or https.';
        }

        if (! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return 'Unsupported scheme. Use http, https, mailto, or tel.';
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            if ($uri->getHost() === '' || filter_var($destination, FILTER_VALIDATE_URL) === false) {
                return 'Malformed URL.';
            }
        } elseif ($scheme === 'mailto') {
            foreach (explode(',', $uri->getPath()) as $recipient) {
                if (filter_var(rawurldecode($recipient), FILTER_VALIDATE_EMAIL) === false) {
                    return 'Malformed email address.';
                }
            }
        } elseif (! preg_match('/^\+?[0-9(). -]+$/', $uri->getPath()) || ! preg_match('/[0-9]/', $uri->getPath())) {
            return 'Malformed telephone number.';
        }

        return null;
    }
}
