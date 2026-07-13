<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Contracts\MarketDataProviderInterface;
use Illuminate\Support\Str;

class ListAvailableDrivers
{
    /**
     * Discover driver classes shipped under Infrastructure/Providers.
     * Each {Name} directory is expected to expose a {Name}Driver class
     * implementing MarketDataProviderInterface.
     *
     * @return list<array{name: string, class: string, slug: string}>
     */
    public function execute(): array
    {
        $drivers = [];

        foreach (glob(app_path('Domain/Market/Infrastructure/Providers/*'), GLOB_ONLYDIR) as $directory) {
            $name = basename($directory);
            $class = "App\\Domain\\Market\\Infrastructure\\Providers\\{$name}\\{$name}Driver";

            if (! class_exists($class) || ! is_subclass_of($class, MarketDataProviderInterface::class)) {
                continue;
            }

            $drivers[] = [
                'name' => Str::headline($name),
                'class' => $class,
                // Default slug matching the ProviderFactory driver keys
                'slug' => Str::snake($name),
            ];
        }

        return $drivers;
    }

    /**
     * @return list<string>
     */
    public function classes(): array
    {
        return array_column($this->execute(), 'class');
    }
}
