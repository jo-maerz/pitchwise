<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use App\Services\PieceService;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class ResetDemo extends Command
{
    protected $signature = 'practice:reset-demo';

    protected $description = 'Put the demo accounts and Demo Music School back as seeded (visitors share the demo password)';

    public function handle(PieceService $pieces): int
    {
        try {
            Demo::password();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // People who joined the school themselves stay members; its pieces go, with every run and annotation on them.
        $school = Organization::firstWhere('name', Demo::SCHOOL);
        if ($school !== null) {
            $school->pieces()->get()->each(fn (Piece $piece) => $pieces->delete($piece));
            $school->folders()->whereNull('parent_id')->delete(); // subfolders follow by cascade
        }

        // Their runs, statistics and annotations go by cascade; Sanctum tokens have no foreign key.
        User::whereIn('email', Demo::emails())->get()->each(function (User $user) {
            $user->tokens()->delete();
            $user->delete();
        });

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->info('The demo is back as seeded.');

        return self::SUCCESS;
    }
}
