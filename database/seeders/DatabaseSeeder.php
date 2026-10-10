<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public const SCHOOL = 'Demo Music School';

    public function run(): void
    {
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
}
