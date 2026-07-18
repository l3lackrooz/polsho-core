<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersionPolicy extends Model
{
    protected $fillable = [
        'platform',
        'latest_version',
        'latest_build',
        'minimum_version',
        'minimum_build',
        'store_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latest_build' => 'integer',
            'minimum_build' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, int|string|null> */
    public function updateStatusFor(string $clientVersion, int $clientBuild): array
    {
        $mode = 'none';
        if ($this->isOlderThan($clientVersion, $clientBuild, $this->minimum_version, $this->minimum_build)) {
            $mode = 'required';
        } elseif ($this->isOlderThan($clientVersion, $clientBuild, $this->latest_version, $this->latest_build)) {
            $mode = 'available';
        }

        return [
            'mode' => $mode,
            'latest_version' => $this->latest_version,
            'latest_build' => $this->latest_build,
            'minimum_version' => $this->minimum_version,
            'minimum_build' => $this->minimum_build,
            'store_url' => $this->store_url,
        ];
    }

    private function isOlderThan(
        string $clientVersion,
        int $clientBuild,
        string $targetVersion,
        int $targetBuild,
    ): bool {
        $comparison = version_compare($clientVersion, $targetVersion);

        return $comparison < 0 || ($comparison === 0 && $clientBuild < $targetBuild);
    }
}
