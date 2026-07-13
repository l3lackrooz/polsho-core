<?php

namespace App\Domain\Market\Infrastructure\Providers;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider as ProviderModel;
use App\Domain\Market\Domain\Contracts\MarketProviderInterface;
use Exception;

class MarketProviderFactory
{
    public function all(): array
    {
        $providers = ProviderModel::where('status', 'active')->get();

        return $providers->map(function ($model) {
            return $this->make($model);
        })->all();
    }

    public function make(ProviderModel $model): MarketProviderInterface
    {
        $driverClass = $this->resolveDriverClass($model->driver);

        if (!class_exists($driverClass)) {
            throw new Exception("Driver class [{$driverClass}] not found.");
        }

        return new $driverClass($model);
    }

    protected function resolveDriverClass(string $driver): string
    {
        return "App\\Domain\\Market\\Infrastructure\\Providers\\Drivers\\" . ucfirst($driver) . "Provider";
    }
}
