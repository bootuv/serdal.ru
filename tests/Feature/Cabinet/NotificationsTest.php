<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Notifications;
use App\Models\Homework;
use App\Models\Room;
use App\Models\User;
use App\Support\CabinetUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Панель уведомлений в новых кабинетах и перевод ссылок старого кабинета. */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    /** Уведомление в старом формате (сохранено до CabinetMessage): ссылка в actions[0].url, иконка heroicon. */
    private function notify(User $user, string $title, string $body, ?string $url = null, bool $read = false): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\Test',
            'data' => array_filter([
                'title' => $title,
                'body' => $body,
                'icon' => 'heroicon-o-academic-cap',
                'actions' => $url ? [['name' => 'view', 'url' => $url]] : null,
                'format' => 'filament',
            ]),
            'read_at' => $read ? now() : null,
        ]);

        return $id;
    }

    public function test_bell_opens_panel_with_new_and_earlier(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $this->notify($student, 'Новое задание', 'Вам назначено: Эссе');
        $this->notify($student, 'Работа оценена', 'Ваша работа получила оценку: 5', read: true);

        $this->actingAs($student)->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('notifications-open')
            ->assertSee('Уведомления, есть новые');

        Livewire::actingAs($student)->test(Notifications::class)
            ->assertDontSee('Новое задание')
            ->dispatch('notifications-open')
            ->assertSee('Новые')
            ->assertSee('Новое задание')
            ->assertSee('Раньше')
            ->assertSee('Работа оценена')
            ->assertDispatched('notifications-count', count: 1)
            ->call('readAll')
            ->assertDispatched('notifications-count', count: 0)
            ->assertDontSee('Прочитать все');

        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_visit_marks_read_and_opens_new_cabinet(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $homework = Homework::create(['teacher_id' => $teacher->id, 'title' => 'Эссе', 'is_visible' => true]);
        $id = $this->notify($student, 'Новое задание', 'Вам назначено: Эссе', url('/student/homework/' . $homework->id));

        Livewire::actingAs($student)->test(Notifications::class)
            ->set('open', true)
            ->call('visit', $id)
            ->assertRedirect(route('cabinet.student.task', $homework));

        $this->assertNotNull($student->notifications()->find($id)->read_at);

        // Чужое уведомление открыть нельзя
        $foreign = $this->notify($teacher, 'Новая работа', 'Сдана работа');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($student)->test(Notifications::class)->call('visit', $foreign);
    }

    public function test_clear_and_empty_state(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->notify($teacher, 'Новый отзыв', 'Ученик оставил вам отзыв');

        Livewire::actingAs($teacher)->test(Notifications::class)
            ->set('open', true)
            ->assertSee('Настроить уведомления')
            ->call('clear')
            ->assertSee('Пока пусто');

        $this->assertSame(0, $teacher->notifications()->count());
    }

    public function test_legacy_links_are_translated(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $room = Room::create(['user_id' => $teacher->id, 'name' => 'Английский', 'meeting_id' => 'm1', 'moderator_pw' => 'a', 'attendee_pw' => 'b']);

        $this->assertSame(route('cabinet.teacher.messages', ['room' => 7]), CabinetUrl::fromLegacy(url('/tutor/messenger?room=7'), $teacher));
        $this->assertSame(route('cabinet.student.messages', ['support' => 1]), CabinetUrl::fromLegacy(url('/student/messenger?support=1'), $student));
        $this->assertSame(route('cabinet.teacher.lesson', ['room' => $room->id]), CabinetUrl::fromLegacy(url('/tutor/rooms/' . $room->id), $teacher));
        $this->assertSame(route('cabinet.teacher.tasks'), CabinetUrl::fromLegacy(url('/tutor/homework/5'), $teacher));
        $this->assertSame(route('cabinet.teacher.subscription'), CabinetUrl::fromLegacy(url('/tutor/subscription'), $teacher));
        $this->assertSame(route('cabinet.student.payments'), CabinetUrl::fromLegacy(url('/student/payment-debts'), $student));
        $this->assertSame(route('cabinet.student.schedule'), CabinetUrl::fromLegacy(url('/student/rooms'), $student));
        // Нет нового экрана — ссылка остаётся прежней
        $this->assertSame(url('/tutor/meeting-sessions/3'), CabinetUrl::fromLegacy(url('/tutor/meeting-sessions/3'), $teacher));
        $this->assertNull(CabinetUrl::fromLegacy(null, $teacher));
    }
}
