<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PwaManifestController extends Controller
{
    public function __invoke(): Response
    {
        $manifestPath = config('app.pwa_manifest_path', public_path('site.webmanifest'));

        abort_unless(is_file($manifestPath) && is_readable($manifestPath), 404);

        $manifest = file_get_contents($manifestPath);

        abort_if($manifest === false, 500);

        $data = json_decode($manifest, true);

        if (is_array($data) && app()->getLocale() === 'fr') {
            $manifest = json_encode($this->translate($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return response($manifest, 200)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'private, no-store');
    }

    private function translate(array $data): array
    {
        $data['lang'] = 'fr';
        if (isset($data['description'])) {
            $data['description'] = __($data['description']);
        }
        foreach ($data['shortcuts'] ?? [] as $index => $shortcut) {
            foreach (['name', 'short_name', 'description'] as $field) {
                if (isset($shortcut[$field])) {
                    $data['shortcuts'][$index][$field] = __($shortcut[$field]);
                }
            }
        }
        foreach ($data['screenshots'] ?? [] as $index => $screenshot) {
            if (isset($screenshot['label'])) {
                $data['screenshots'][$index]['label'] = __($screenshot['label']);
            }
        }

        return $data;
    }
}
