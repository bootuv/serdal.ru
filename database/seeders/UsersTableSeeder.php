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
    public const COUNT_USERS = 10;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->truncate();

        $data = [];

        for ($i = 0; $i < self::COUNT_USERS; $i++) {
            $user = User::create([
                'name' => fake()->name,
                'email' => fake()->email,
                'username' => "user-" . Str::random(6),
                'grade' => [
                    'preschool' => rand(0, 1) === 1 ? true : false,
                    'school' => collect(range(1, 11))
                        ->random(random_int(1, 11))
                        ->sort()
                        ->values()
                        ->all(),
                    'adults' => rand(0, 1) === 1 ? true : false,
                ],
                'password' => Hash::make('password'),
                'avatar' => 'images/Rectangle-4.png',
                'status' => 'Продолжаю прием в группы и индивидуальников',
                'role' => match(rand(0, 2)) {
                    0 => User::ROLE_STUDENT,
                    1 => User::ROLE_TUTOR,
                    2 => User::ROLE_MENTOR,
                },
                'about' => 'Я учитель математики и физики, занимаюсь с учениками 9-10 классов. У меня есть опыт работы в школе и в частных репетиторских центрах.',
                'extra_info' => 'Я люблю математику и физику, и хочу помочь ученикам понять эти предметы.',
                'phone' => '+79999999999',
                'whatsup' => '79999999999',
                'instagram' => 'example',
                'telegram' => 'example',
            ]);

            $user->subjects()->attach(rand(0, 1) === 1 ? [1,2] : [1,3]);
            $user->directs()->attach([1,3,4]);
        }
    }
}
