<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

class LocaleResolver
{
    public function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $language = strtolower(explode('-', str_replace('_', '-', trim($value)))[0]);

        return array_key_exists($language, config('localization.supported')) ? $language : null;
    }

    public function resolve(Request $request): string
    {
        return $this->normalize($request->user()?->preferred_locale)
            ?? ($request->routeIs('password.reset') ? $this->normalize($request->query('locale')) : null)
            ?? $this->detect($request);
    }

    public function establishLocale(User $user, Request $request): string
    {
        $user->refresh();

        if ($user->preferred_locale === null) {
            User::query()->whereKey($user->id)->whereNull('preferred_locale')
                ->update(['preferred_locale' => $this->detect($request)]);

            $user->refresh();
        }

        return $this->normalize($user->preferred_locale) ?? config('localization.fallback');
    }

    private function detect(Request $request): string
    {
        $selected = $this->normalize($request->cookie(config('localization.cookie')));

        if ($selected !== null) {
            return $selected;
        }

        foreach (AcceptHeader::fromString($request->header('Accept-Language', ''))->all() as $language) {
            if ($language->getQuality() > 0 && ($locale = $this->normalize($language->getValue())) !== null) {
                return $locale;
            }
        }

        return config('localization.fallback');
    }
}
