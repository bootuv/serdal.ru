<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\User;

class MeetingSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (User::isSpecialist()->get() as $teacher) {
            $room = Room::create([
                'user_id' => $teacher->id,
                'name' => 'Кабинет — ' . $teacher->name,
                'meeting_id' => (string) Str::uuid(),
                'moderator_pw' => Str::random(8),
                'attendee_pw' => Str::random(8),
            ]);

            // Разброс активности: часть преподавателей почти не ведёт занятия,
            // часть — очень активна, чтобы сортировка на главной была наглядной
            $sessionsCount = match (random_int(0, 3)) {
                0 => random_int(0, 2),
                1 => random_int(3, 10),
                2 => random_int(11, 20),
                3 => random_int(21, 40),
            };

            for ($i = 0; $i < $sessionsCount; $i++) {
                $start = now()
                    ->subDays(random_int(0, 29))
                    ->setTime(random_int(9, 20), collect([0, 15, 30, 45])->random());

                MeetingSession::create([
                    'user_id' => $teacher->id,
                    'room_id' => $room->id,
                    'meeting_id' => $room->meeting_id,
                    'started_at' => $start,
                    'ended_at' => $start->copy()->addMinutes(random_int(40, 90)),
                    'status' => 'completed',
                    'participant_count' => random_int(1, 8),
                ]);
            }
        }
    }
}
