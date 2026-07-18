<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\BrandingManifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/pub/branding
 *
 * Serves the branding manifest (exchange logos + asset icons) with the
 * manifest version as a strong ETag. Clients revalidate with If-None-Match:
 * an unchanged manifest costs a 304 with an empty body. The image URLs
 * inside are content-addressed, so they never need revalidation at all.
 */
class PublicBrandingController extends Controller
{
    public function show(Request $request, BrandingManifest $manifest): Response
    {
        $document = $manifest->build();
        $etag = '"'.$document['version'].'"';

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, no-cache',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->noContent(304, $headers);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $document,
        ], 200, $headers);
    }
}
