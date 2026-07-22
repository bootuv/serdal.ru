<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = User::where('role', User::ROLE_STUDENT)->get();
        $teachers = User::isSpecialist()->get();

        if ($students->isEmpty() || $teachers->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 100; $i++) {
            $this->createReview($students, $teachers->random()->id);
        }

        // Одному преподавателю — 30 отзывов, чтобы на его странице была видна подгрузка
        $featured = $teachers->first();
        for ($i = 0; $i < 30; $i++) {
            $this->createReview($students, $featured->id);
        }
    }

    private function createReview($students, int $teacherId): void
    {
        Review::create([
            'user_id' => $students->random()->id,
            'teacher_id' => $teacherId,
            'rating' => random_int(3, 5),
            'text' => fake()->realText(random_int(80, 200)),
            'teacher_read_at' => random_int(0, 1) ? now()->subDays(random_int(1, 10)) : null,
        ]);
    }
}
