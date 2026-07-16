<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default backoffice administrator.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@backoffice.local'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'is_admin' => true,
            ],
        );
    }
}
