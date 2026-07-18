<?php

namespace App\Domain\Suggestion;

use Illuminate\Support\ServiceProvider;

class SuggestionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Domain/Suggestion/Infrastructure/Persistence/Migrations'));
    }
}
