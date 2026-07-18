<?php

namespace App\Domain\Shared\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores branding images (exchange logos, asset icons) on the public disk
 * under content-addressed names: `branding/{slug}.{hash8}.{ext}`.
 *
 * The content hash in the filename makes every URL immutable — clients cache
 * an image forever, and changing an image changes its URL, so the manifest
 * diff is the only invalidation mechanism needed.
 */
class BrandingStorage
{
    private const DIRECTORY = 'branding';

    private const DISK = 'public';

    /** Stores the file and returns its disk path (e.g. branding/nobitex.a3f8c1d2.png). */
    public function store(UploadedFile $file, string $slug): string
    {
        $hash = substr(sha1_file($file->getRealPath()) ?: Str::random(40), 0, 8);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = Str::slug($slug).'.'.$hash.'.'.$extension;

        return $file->storeAs(self::DIRECTORY, $name, self::DISK);
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
