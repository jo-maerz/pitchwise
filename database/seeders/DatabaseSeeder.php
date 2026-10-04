<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** php artisan migrate --seed: a demo login, the catalogue, and some practice history. */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Violinist', 'password' => 'password', 'email_verified_at' => now()],
        );

        $this->call([
            CatalogueSeeder::class,
            DemoHistorySeeder::class,
        ]);
    }
}
