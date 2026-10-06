<?php

namespace App\Services;

use App\Models\Announcement;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnnouncementImageService
{
    /** @return array<string, bool> Image paths mapped to whether an announcement uses them. */
    public function images(string $folder, bool $unusedOnly = false): array
    {
        if (! in_array($folder, ['hero', 'social'], true)) {
            throw new InvalidArgumentException('Unknown announcement image folder.');
        }

        $prefix = 'images/updates/'.$folder.'/';
        $directory = public_path($prefix);
        if (! is_dir($directory)) {
            return [];
        }

        $used = [];
        foreach (Announcement::query()->select(['hero_image_path', 'social_image_path', 'content'])->cursor() as $announcement) {
            foreach ([$announcement->hero_image_path, $announcement->social_image_path] as $reference) {
                $used[$this->localPath($reference)] = true;
            }

            $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.Str::markdown($announcement->content).'</body></html>', LIBXML_NOERROR, 'UTF-8');
            foreach ($document->querySelectorAll('img[src]') as $image) {
                $used[$this->localPath($image->getAttribute('src'))] = true;
            }
        }

        $images = [];
        foreach (File::allFiles($directory) as $file) {
            if (! str_starts_with((string) $file->getRealPath(), realpath($directory).DIRECTORY_SEPARATOR)
                || ! in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'svg'], true)) {
                continue;
            }

            $path = $prefix.$file->getRelativePathname();
            $isUsed = isset($used[$path]);
            if (! $unusedOnly || ! $isUsed) {
                $images[$path] = $isUsed;
            }
        }

        ksort($images);

        return $images;
    }

    private function localPath(?string $reference): string
    {
        $host = parse_url(trim($reference ?? ''), PHP_URL_HOST);
        if ($host !== null && $host !== parse_url(asset('/'), PHP_URL_HOST)) {
            return '';
        }

        return ltrim(rawurldecode((string) parse_url(trim($reference ?? ''), PHP_URL_PATH)), '/');
    }
}
