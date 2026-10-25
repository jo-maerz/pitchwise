<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Database\Seeder;

/** The demo accounts, Demo Music School's library and the demo user's practice history. practice:reset-demo runs it again every night. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Demo::password();
        $school = Organization::firstOrCreate(['name' => Demo::SCHOOL]);

        foreach (Demo::accounts() as $email => $account) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $account['name'],
                'role' => $account['role'] ?? Role::User,
                'organization_id' => isset($account['school']) ? $school->id : null,
                'password' => $password,
            ]);
            // A second save: moving an existing user to another organization clears their instruments.
            $user->update(['annotation_instruments' => $account['annotation_instruments'] ?? null]);
            // Demo accounts have no inbox, so they are verified even when they were seeded before verification existed.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        $this->call([
            DemoSchoolSeeder::class,
            DemoHistorySeeder::class,
        ]);
    }
}
