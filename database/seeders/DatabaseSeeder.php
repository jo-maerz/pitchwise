<?php

namespace Database\Seeders;

use App\Support\Demo;
use Illuminate\Database\Seeder;

/** Everything for trying the app out: the shared library, then the demo accounts with their school. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Demo::password(); // refuses on a public site without DEMO_PASSWORD, before anything is written

        $this->call([
            CatalogueSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
