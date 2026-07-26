<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\Services\ProviderProfileVersion;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProviderProfileCacheController extends Controller
{
    use RespondsWithApi;

    /** Forces mobile clients to drop their persisted provider-profile data. */
    public function refresh(ProviderProfileVersion $version): JsonResponse
    {
        return $this->respond([
            'version' => $version->refresh(),
        ]);
    }
}
