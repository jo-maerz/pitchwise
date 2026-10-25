<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public const SCHOOL = 'Demo Music School';

    public function run(): void
    {
        $this->refuseOnPublicSites();

        $school = Organization::firstOrCreate(['name' => self::SCHOOL]);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Demo Admin', 'password' => 'password', 'email_verified_at' => now(), 'role' => Role::Admin],
        );
        User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            ['name' => 'Demo Teacher', 'password' => 'password', 'email_verified_at' => now(), 'role' => Role::OrgAdmin, 'organization_id' => $school->id],
        );
        User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Violinist', 'password' => 'password', 'email_verified_at' => now(), 'organization_id' => $school->id, 'annotation_instruments' => ['violin', 'viola', 'cello']],
        );

        $this->call([
            CatalogueSeeder::class,
            DemoSchoolSeeder::class,
            DemoHistorySeeder::class,
        ]);
    }

    /** Every demo account has the password "password": fine on this machine, never on a site others can reach. */
    private function refuseOnPublicSites(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (app()->isProduction() && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException("Demo data is not seeded on {$host}: every demo account has the password \"password\". Seed CatalogueSeeder alone for the shared library.");
        }
    }
}
