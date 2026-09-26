<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Recordings;
use App\Models\Recording;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Livewire;
use Tests\TestCase;

/** Записи занятий учителя в новом кабинете (/cabinet/teacher/recordings). */
class TeacherRecordingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
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

    private function room(User $teacher, array $students, string $name, string $type = 'individual'): Room
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => $type,
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        foreach ($students as $s) {
            $room->participants()->attach($s->id);
            $teacher->students()->syncWithoutDetaching([$s->id]);
        }

        return $room;
    }

    private function recording(Room $room, $endedAt, array $attrs = []): Recording
    {
        return Recording::create($attrs + [
            'meeting_id' => $room->meeting_id,
            'record_id' => 'rec-' . uniqid(),
            'name' => $room->name,
            'start_time' => $endedAt->copy()->subMinutes(55),
            'end_time' => $endedAt,
            's3_url' => 'https://s3.example/recordings/' . uniqid() . '.mp4',
        ]);
    }

    private function retention(User $teacher, int $days): void
    {
        $tariff = Tariff::create(['name' => 'Профи', 'slug' => 'tariff-' . uniqid(), 'recording_retention_days' => $days]);
        Subscription::create([
            'user_id' => $teacher->id,
            'tariff_id' => $tariff->id,
            'status' => Subscription::STATUS_ACTIVE,
            'price' => 0,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.recordings'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.recordings'))
            ->assertRedirect(route('cabinet.student.home'));

        // Чужую запись не открыть в плеере
        $stranger = $this->user(User::ROLE_TUTOR);
        $foreign = $this->recording($this->room($stranger, [], 'Чужое'), now()->subDay());
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.teacher.recordings', ['open' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_teacher_sees_own_recordings_soon_and_by_week(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->retention($teacher, 90);
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $math = $this->room($teacher, [$ivan], 'Математика');
        $group = $this->room($teacher, [$this->user(User::ROLE_STUDENT), $this->user(User::ROLE_STUDENT)], 'ЕГЭ-2027', 'group');

        $this->recording($math, now()->subDays(88)); // удалится через ~2 дня
        $this->recording($group, now()->subHours(3));
        $this->recording($this->room($this->user(User::ROLE_TUTOR), [], 'Чужое занятие'), now()->subDay());

        $this->actingAs($teacher)
            ->get(route('cabinet.teacher.recordings'))
            ->assertOk()
            ->assertSee('Хранятся 90 дней по тарифу «Профи»')
            ->assertSee('Скоро удалятся')
            ->assertSee('Математика · Иван Петров')
            ->assertSee('Удалится через')
            ->assertSee('Группа «ЕГЭ-2027»')
            ->assertSee('Эта неделя')
            ->assertDontSee('Выбрать')
            ->assertDontSee('Чужое занятие');

        Queue::assertPushed(\App\Jobs\SyncUserRecordings::class);
    }

    public function test_player_opens_own_recording(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $rec = $this->recording($this->room($teacher, [$this->user(User::ROLE_STUDENT)], 'Английский'), now()->subDay());

        Livewire::actingAs($teacher)->test(Recordings::class)
            ->call('play', $rec->id)
            ->assertSet('open', $rec->id)
            ->assertSee($rec->s3_url, false)
            ->assertSee(route('recordings.download', $rec), false);
    }

    public function test_open_recording_is_deleted_from_menu(): void
    {
        Bigbluebutton::shouldReceive('deleteRecordings')->once()->andReturn(collect(['returncode' => 'SUCCESS']));

        $teacher = $this->user(User::ROLE_TUTOR);
        $room = $this->room($teacher, [$this->user(User::ROLE_STUDENT)], 'Английский');
        $open = $this->recording($room, now()->subDay());
        $keep = $this->recording($room, now()->subDays(2));

        Livewire::actingAs($teacher)->test(Recordings::class)
            ->call('play', $open->id)
            ->assertSee('Удалить запись')
            ->call('askDelete')
            ->assertSet('confirmDelete', true)
            ->assertSee('Удалить запись?')
            ->call('deleteOpen')
            ->assertSet('open', null)
            ->assertSet('confirmDelete', false)
            ->assertDispatched('toast', message: 'Запись удалена');

        $this->assertSoftDeleted($open);
        $this->assertNotSoftDeleted($keep);
    }

    public function test_foreign_recording_cannot_be_opened_or_deleted(): void
    {
        Bigbluebutton::shouldReceive('deleteRecordings')->never();

        $teacher = $this->user(User::ROLE_TUTOR);
        $foreign = $this->recording($this->room($this->user(User::ROLE_TUTOR), [], 'Чужое'), now()->subDay());

        Livewire::actingAs($teacher)->test(Recordings::class)
            ->call('play', $foreign->id)
            ->assertNotFound();

        // Подставленный id в обход плеера — сервис удаляет только свои записи
        $this->assertSame(0, app(\App\Services\TeacherRecordingsService::class)->delete($teacher, [$foreign->id]));
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_soon_block_contains_oldest_recordings_even_beyond_the_list(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->retention($teacher, 90);
        $room = $this->room($teacher, [$this->user(User::ROLE_STUDENT, 'Иван Петров')], 'Математика');

        // 70 свежих записей — больше, чем помещается в список (60)
        foreach (range(1, 70) as $i) {
            $this->recording($room, now()->subHours($i * 3), ['name' => 'Свежая ' . $i]);
        }
        // Самые старые: удалятся через ~1 и ~3 дня
        $oldest = $this->recording($room, now()->subDays(89), ['name' => 'Старейшая']);
        $older = $this->recording($room, now()->subDays(87), ['name' => 'Старая']);
        // Удалится через 30 дней — не «скоро»
        $this->recording($room, now()->subDays(60), ['name' => 'Средняя']);

        $html = Livewire::actingAs($teacher)->test(Recordings::class)
            ->assertSee('Скоро удалятся')
            ->assertSeeInOrder(['Скоро удалятся', 'rec-' . $oldest->id, 'rec-' . $older->id, 'Эта неделя'], false)
            ->html();

        // Первыми — те, что удалятся раньше; в «Скоро удалятся» только они
        $soonBlock = substr($html, strpos($html, 'Скоро удалятся'), strpos($html, 'Эта неделя') - strpos($html, 'Скоро удалятся'));
        $this->assertStringContainsString('rec-' . $oldest->id, $soonBlock);
        $this->assertSame(2, substr_count($soonBlock, 'wire:key="rec-'));
    }

    public function test_search_and_statuses(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');
        $math = $this->room($teacher, [$ivan], 'Математика');
        $english = $this->room($teacher, [$this->user(User::ROLE_STUDENT, 'Алина Смирнова')], 'Английский');

        $this->recording($math, now()->subDay());
        $this->recording($english, now()->subDays(2), ['s3_url' => null, 'url' => 'https://bbb.example/playback/video/1/']);
        $this->recording($english, now()->subMinutes(30), ['s3_url' => null, 'url' => null, 'start_time' => now()->subMinutes(80)]);

        Livewire::actingAs($teacher)->test(Recordings::class)
            ->assertSee('Загружается')
            ->assertSee('Обрабатывается')
            ->set('search', 'Иван')
            ->assertSee('Математика · Иван Петров')
            ->assertDontSee('Английский · Алина Смирнова')
            ->set('search', 'Английск')
            ->assertSee('Английский · Алина Смирнова')
            ->assertDontSee('Математика · Иван Петров')
            ->set('search', 'Физика')
            ->assertSee('Ничего не нашлось')
            ->assertSee('Сбросить поиск');
    }
}
