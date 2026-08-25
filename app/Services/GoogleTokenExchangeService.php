<?php

namespace App\Services;

use App\Exceptions\GoogleIdentityConflictException;
use App\Exceptions\GooglePasswordProofRequiredException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleTokenExchangeService
{
    public function resolve(GoogleIdentity $identity, ?string $passwordProof): User
    {
        return DB::transaction(function () use ($identity, $passwordProof): User {
            $subjectOwner = User::query()
                ->where('google_subject', $identity->subject)
                ->lockForUpdate()
                ->first();
            $emailOwner = User::query()
                ->where('email', $identity->email)
                ->lockForUpdate()
                ->first();

            if ($subjectOwner !== null) {
                if ($emailOwner !== null && ! $subjectOwner->is($emailOwner)) {
                    throw new GoogleIdentityConflictException;
                }

                return $subjectOwner;
            }

            if ($emailOwner === null) {
                $user = new User([
                    'name' => $identity->name ?? $identity->email,
                    'email' => $identity->email,
                    'password' => Hash::make(Str::random(64)),
                    'avatar_url' => $identity->avatarUrl,
                ]);
                $user->forceFill(['google_subject' => $identity->subject]);
                $user->email_verified_at = now();
                $user->save();

                return $user;
            }

            if ($emailOwner->google_subject !== null) {
                throw new GoogleIdentityConflictException;
            }

            if (! $identity->hasAuthoritativeEmail()
                && (! is_string($passwordProof) || ! Hash::check($passwordProof, $emailOwner->password))) {
                throw new GooglePasswordProofRequiredException;
            }

            $emailOwner->forceFill(['google_subject' => $identity->subject])->save();

            return $emailOwner;
        });
    }

    public function resolveWebUser(string $email, ?string $subject, string $name, ?string $avatarUrl): User
    {
        $email = Str::lower($email);

        return DB::transaction(function () use ($email, $subject, $name, $avatarUrl): User {
            $subjectOwner = filled($subject)
                ? User::query()->where('google_subject', $subject)->lockForUpdate()->first()
                : null;
            $emailOwner = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($subjectOwner !== null) {
                if ($emailOwner !== null && ! $subjectOwner->is($emailOwner)) {
                    throw new GoogleIdentityConflictException;
                }

                return $subjectOwner;
            }

            if ($emailOwner !== null) {
                if ($emailOwner->google_subject !== null) {
                    throw new GoogleIdentityConflictException;
                }

                if (filled($subject)) {
                    $emailOwner->forceFill(['google_subject' => $subject])->save();
                }

                return $emailOwner;
            }

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'avatar_url' => $avatarUrl,
            ]);

            if (filled($subject)) {
                $user->forceFill(['google_subject' => $subject]);
            }

            $user->save();

            return $user;
        });
    }
}
