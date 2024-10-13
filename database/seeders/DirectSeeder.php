<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class DirectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('directs')->insert([
            ['name' => 'ЕГЭ'],
            ['name' => 'ОГЭ'],
            ['name' => 'Олимпиады'],
            ['name' => 'ОПР'],
            ['name' => 'Начальная школа'],
            ['name' => 'IT-наставничество'],
            ['name' => 'ДВИ'],
            ['name' => 'ВПР'],
            ['name' => 'Язык с нуля'],
        ]);
    }
}
