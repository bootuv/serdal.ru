<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->call([
            SubjectSeeder::class,
            DirectSeeder::class,
            UsersTableSeeder::class,
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
