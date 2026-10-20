<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // No demo users or customer data in production by default.
        // Create the administrator explicitly: php artisan admin:create
    }
}
