<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantBackofficeAdminCommand extends Command
{
    protected $signature = 'backoffice:grant-admin {email : Email address of the administrator}';

    protected $description = 'Grant an existing user access to the backoffice';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("{$user->email} already has administrator access.");

            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);
        $this->info("Granted backoffice access to {$user->email}.");

        return self::SUCCESS;
    }
}
