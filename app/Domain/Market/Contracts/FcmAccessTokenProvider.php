<?php

namespace App\Domain\Market\Contracts;

interface FcmAccessTokenProvider
{
    public function token(): string;
}
