<?php

namespace App\Services;

use App\Models\Announcement;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnnouncementImageService
{
    /** @var array<string, array<string, bool>> */
    private array $imagesByFolder = [];

    /** @var array<string, list<string>> */
    private array $pathsByFolder = [];

    /** @var array<string, bool>|null */
    private ?array $usedPaths = null;

    public function hasImages(string $folder): bool
    {
        return $this->imagePaths($folder) !== [];
    }

    /** @return array<string, bool> Image paths mapped to whether an announcement uses them. */
    public function images(string $folder, bool $unusedOnly = false): array
    {
        if (! array_key_exists($folder, $this->imagesByFolder)) {
            $paths = $this->imagePaths($folder);
            $used = $paths === [] ? [] : $this->usedPaths();
            $images = [];
            foreach ($paths as $path) {
                $images[$path] = isset($used[$path]);
            }
            ksort($images);
            $this->imagesByFolder[$folder] = $images;
        }

        return $unusedOnly
            ? array_filter($this->imagesByFolder[$folder], fn (bool $used): bool => ! $used)
            : $this->imagesByFolder[$folder];
    }

    /** @return list<string> */
    private function imagePaths(string $folder): array
    {
        if (! in_array($folder, ['hero', 'social'], true)) {
            throw new InvalidArgumentException('Unknown announcement image folder.');
        }

        if (array_key_exists($folder, $this->pathsByFolder)) {
            return $this->pathsByFolder[$folder];
        }

        $prefix = 'images/updates/'.$folder.'/';
        $directory = public_path($prefix);
        $paths = [];
        if (is_dir($directory)) {
            foreach (File::allFiles($directory) as $file) {
                if (! str_starts_with((string) $file->getRealPath(), realpath($directory).DIRECTORY_SEPARATOR)
                    || ! in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'svg'], true)) {
                    continue;
                }
                $paths[] = $prefix.$file->getRelativePathname();
            }
        }

        return $this->pathsByFolder[$folder] = $paths;
    }

    /** @return array<string, bool> */
    private function usedPaths(): array
    {
        if ($this->usedPaths !== null) {
            return $this->usedPaths;
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

        return $this->usedPaths = $used;
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
