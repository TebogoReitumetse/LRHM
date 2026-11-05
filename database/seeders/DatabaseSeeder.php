<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data needed in every environment.
        $this->call(ReferenceDataSeeder::class);

        // Demo accounts and sample records for testing / UAT only.
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
