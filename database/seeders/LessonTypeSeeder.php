<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonType;
use App\Models\User;

class LessonTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Получаем всех пользователей
        $users = User::isSpecialist()->get();
        
        foreach ($users as $user) {
            // Создаем по 2 типа уроков для каждого пользователя
            for ($i = 0; $i < 2; $i++) {
                LessonType::create([
                    'price' => random_int(400, 1200),
                    'count_per_week' => random_int(1, 5),
                    'duration' => random_int(40, 90),
                    'type' => $i === 0 ? LessonType::TYPE_GROUP : LessonType::TYPE_INDIVIDUAL,
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
