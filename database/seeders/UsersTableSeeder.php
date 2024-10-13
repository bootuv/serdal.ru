<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;
use Str;
use Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->truncate();

        $data = [];

        for ($i = 0; $i < 3; $i++) {
            $user = User::create([
                'name' => Str::random(10),
                'email' => Str::random(10).'@example.com',
                'username' => "user-" . Str::random(6),
                'grade' => "9-10 классы",
                'password' => Hash::make('password'),
                'status' => 'Продолжаю прием в группы и индивидуальников',
            ]);

            $user->subjects()->attach([1,2]);
            $user->directs()->attach([1,3,4]);
        }
    }
}
