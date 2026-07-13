<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\AssetDTO;
use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Support\Facades\DB;

class UpdateAsset
{
    public function execute(Asset $asset, AssetDTO $data): Asset
    {
        return DB::transaction(function () use ($asset, $data): Asset {
            $asset->update($data->toArray());

            return $asset->refresh();
        });
    }
}
