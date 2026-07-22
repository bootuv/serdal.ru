<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;
use Schema;
use Str;
use Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    public const COUNT_TEACHERS = 45;

    public const COUNT_STUDENTS = 10;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();

        $data = [];

        User::create([
            'name' => 'Berd',
            'email' => 'elberd06@gmail.com',
            'username' => 'elberd06',
            'password' => bcrypt('123123'),
            'role' => User::ROLE_ADMIN,
        ]);

        for ($i = 0; $i < self::COUNT_TEACHERS; $i++) {
            $this->createUser(rand(0, 3) === 0 ? User::ROLE_MENTOR : User::ROLE_TUTOR);
        }

        for ($i = 0; $i < self::COUNT_STUDENTS; $i++) {
            $this->createUser(User::ROLE_STUDENT);
        }
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'name' => fake()->name,
            'email' => fake()->unique()->email,
            'username' => "user-" . Str::random(6),
            'grade' => collect([...range(1, 11), 'preschool', 'adults'])
                ->random(random_int(1, 11))
                ->sort()
                ->values()
                ->all(),
            'password' => Hash::make('password'),
            'avatar' => null,
            'status' => 'Продолжаю прием в группы и индивидуальников',
            'role' => $role,
            'about' => 'Я учитель математики и физики, занимаюсь с учениками 9-10 классов. У меня есть опыт работы в школе и в частных репетиторских центрах.',
            'extra_info' => 'Я люблю математику и физику, и хочу помочь ученикам понять эти предметы.',
            'phone' => '+79999999999',
            'whatsup' => '79999999999',
            'instagram' => 'example',
            'telegram' => 'example',
        ]);

        $user->subjects()->attach(rand(0, 1) === 1 ? [1, 2] : [1, 3]);
        $user->directs()->attach(collect(range(1, 9))->random(random_int(2, 4))->values()->all());

        return $user;
    }
}
