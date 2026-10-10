<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'practice:make-admin {email}';

    protected $description = 'Give a registered user the admin role (the first admin cannot be made from the website)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No user with this email. Register first, then run this again.');

            return self::FAILURE;
        }
        $user->update(['role' => Role::Admin]);
        // Whoever can run this vouches for the address, so the first admin is not stuck waiting for mail.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        $this->info("{$user->name} is now an admin.");

        return self::SUCCESS;
    }
}
