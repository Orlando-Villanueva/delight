<?php

namespace App\Services;

use Illuminate\Support\Str;

readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
        public int $expiresAt,
        public ?string $hostedDomain,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}

    public function hasAuthoritativeEmail(): bool
    {
        return Str::endsWith(Str::lower($this->email), '@gmail.com')
            || ($this->emailVerified && filled($this->hostedDomain));
    }
}
