<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Subject::create([
            'name' => 'Русский язык',
        ]);

        Subject::create([
            'name' => 'Литература',
        ]);

        Subject::create([
            'name' => 'Математика',
        ]);
    }
}
