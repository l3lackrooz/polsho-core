<?php

namespace App\Domain\Asset;

use Illuminate\Support\ServiceProvider;

class AssetServiceProvider extends ServiceProvider
{


    public function boot(): void
    {
        // load migrations from module
        $this->loadMigrationsFrom(
            app_path('Domain/Asset/Infrastructure/Persistence/Migrations')
        );

        // اگر بعداً config یا routes داشتی:
        // $this->loadRoutesFrom(...);
        // $this->mergeConfigFrom(...);
    }
}
