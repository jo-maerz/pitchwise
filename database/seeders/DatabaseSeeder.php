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

        $demoUsers = [
            'admin@example.com' => ['name' => 'Demo Admin', 'role' => Role::Admin],
            'teacher@example.com' => ['name' => 'Demo Teacher', 'role' => Role::OrgAdmin, 'organization_id' => $school->id],
            'demo@example.com' => ['name' => 'Demo Violinist', 'organization_id' => $school->id, 'annotation_instruments' => ['violin', 'viola', 'cello']],
            'private@example.com' => ['name' => 'Demo Private User'],
        ];
        foreach ($demoUsers as $email => $attributes) {
            $user = User::firstOrCreate(['email' => $email], $attributes + ['password' => 'password']);
            // Demo accounts have no inbox, so they are verified even when they were seeded before verification existed.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

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
