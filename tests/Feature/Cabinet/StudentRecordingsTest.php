<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Recordings;
use App\Models\Recording;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Записи занятий ученика в новом кабинете (/cabinet/student/recordings). */
class StudentRecordingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, ?string $name = null): User
    {
        return User::factory()->create(array_filter([
            'role' => $role,
            'name' => $name,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], fn ($v) => $v !== null));
    }

    private function room(User $teacher, ?User $student, string $name): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        if ($student) {
            $room->participants()->attach($student->id);
            $teacher->students()->syncWithoutDetaching([$student->id]);
        }

        return $room;
    }

    private function recording(Room $room, $endedAt, array $attrs = []): Recording
    {
        return Recording::create($attrs + [
            'meeting_id' => $room->meeting_id,
            'record_id' => 'rec-' . uniqid(),
            'name' => $room->name,
            'start_time' => $endedAt->copy()->subMinutes(60),
            'end_time' => $endedAt,
            's3_url' => 'https://s3.example/recordings/' . uniqid() . '.mp4',
        ]);
    }

    private function retention(User $teacher, int $days): void
    {
        $tariff = Tariff::create(['name' => 'Тариф', 'slug' => 'tariff-' . uniqid(), 'recording_retention_days' => $days]);
        Subscription::create([
            'user_id' => $teacher->id,
            'tariff_id' => $tariff->id,
            'status' => Subscription::STATUS_ACTIVE,
            'price' => 0,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.recordings'))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_recordings(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.recordings'))
            ->assertForbidden();
    }

    public function test_student_without_recordings_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.recordings'))
            ->assertOk()
            ->assertSee('Записей пока нет');
    }

    public function test_student_sees_only_recordings_of_own_teachers(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $student = $this->user(User::ROLE_STUDENT);
        $this->recording($this->room($teacher, $student, 'Английский язык'), now()->subDays(20));

        $stranger = $this->user(User::ROLE_TUTOR);
        $this->recording($this->room($stranger, null, 'Чужое занятие'), now()->subDays(3));

        $this->actingAs($student)
            ->get(route('cabinet.student.recordings'))
            ->assertOk()
            ->assertSee('Английский язык · Мария Соколова')
            ->assertSee('60 минут')
            ->assertDontSee('Чужое занятие')
            ->assertDontSee('Скоро удалятся');
    }

    public function test_recordings_close_to_deletion_are_in_focus(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $this->retention($teacher, 30);
        $room = $this->room($teacher, $student, 'Английский язык');
        $soon = $this->recording($room, now()->subDays(26)->addHours(12));
        $this->recording($room, now()->subDays(2), ['name' => 'Свежая запись']);

        $this->actingAs($student)
            ->get(route('cabinet.student.recordings'))
            ->assertOk()
            ->assertSee('Каждая запись хранится 30 дней')
            ->assertSee('Скоро удалятся')
            ->assertSee('Удалится через 4 дня')
            ->assertSee(route('recordings.download', $soon), false)
            ->assertSee('Свежая запись');
    }

    public function test_student_opens_recording_in_player(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $recording = $this->recording($this->room($teacher, $student, 'Математика'), now()->subDays(1));

        $this->actingAs($student)
            ->get(route('cabinet.student.recordings', ['open' => $recording->id]))
            ->assertOk()
            ->assertSee('Открытая запись')
            ->assertSee($recording->s3_url, false)
            ->assertSee(route('recordings.download', $recording), false);

        Livewire::actingAs($student)
            ->test(Recordings::class)
            ->assertDontSee('Открытая запись')
            ->call('play', $recording->id)
            ->assertSet('open', $recording->id)
            ->assertSee('Открыта')
            ->call('close')
            ->assertSet('open', null);
    }

    public function test_student_cannot_open_foreign_recording(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $foreign = $this->recording($this->room($this->user(User::ROLE_TUTOR), null, 'Чужое'), now()->subDay());

        $this->actingAs($student)
            ->get(route('cabinet.student.recordings', ['open' => $foreign->id]))
            ->assertNotFound();

        Livewire::actingAs($student)
            ->test(Recordings::class)
            ->call('play', $foreign->id)
            ->assertNotFound();
    }

    public function test_filter_by_teacher(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $en = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $math = $this->user(User::ROLE_TUTOR, 'Иван Орлов');
        $this->recording($this->room($en, $student, 'Английский язык'), now()->subDays(1));
        $this->recording($this->room($math, $student, 'Математика'), now()->subDays(2));

        Livewire::actingAs($student)
            ->test(Recordings::class)
            ->assertSee('Английский язык')
            ->assertSee('Математика')
            ->set('teacher', (string) $en->id)
            ->assertSee('Английский язык')
            ->assertDontSee('Математика');
    }

    public function test_old_cabinet_uses_same_access_rules(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $own = $this->recording($this->room($teacher, $student, 'Своё'), now()->subDay());
        $foreign = $this->recording($this->room($this->user(User::ROLE_TUTOR), null, 'Чужое'), now()->subDay());

        $this->assertSame([$own->id], Recording::forStudent($student)->listed()->pluck('id')->all());
        $this->assertNotContains($foreign->id, Recording::forStudent($student)->pluck('id')->all());
    }
}
