<?php

namespace App\Contracts;

use App\Services\GoogleIdentity;

interface GoogleIdTokenVerifierContract
{
    public function verify(string $idToken): ?GoogleIdentity;
}
