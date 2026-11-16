<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            WebsiteSettingsSeeder::class,
            SupportMethodsSeeder::class,
            AnaInsanPreviewSeeder::class,
            NewsItemSeeder::class,
        ]);
    }
}
