<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\AssetDTO;
use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Support\Facades\DB;

class CreateAsset
{
    public function execute(AssetDTO $data): Asset
    {
        return DB::transaction(
            fn (): Asset => Asset::query()->create($data->toArray())
        );
    }
}
