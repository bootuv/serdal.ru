<?php

namespace Tests\Feature\Cabinet;

use App\Jobs\SendSupportMessageTelegramNotification;
use App\Jobs\SendUnreadMessageNotification;
use App\Jobs\SendUnreadSupportMessageNotification;
use App\Livewire\Cabinet\Messages;
use App\Models\Message;
use App\Models\PersonalChat;
use App\Models\Room;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\MessengerService;
use App\Services\StudentTeachersService;
use App\Services\TeacherStudentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** «Сообщения» в новых кабинетах (/cabinet/teacher/messages, /cabinet/student/messages). */
class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alina;

    private User $ivan;

    private Room $single;

    private Room $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Event::fake();
        Storage::fake('s3');

        $this->teacher = $this->user(User::ROLE_TUTOR, 'Мария Соколова');
        $this->alina = $this->user(User::ROLE_STUDENT, 'Алина Смирнова');
        $this->ivan = $this->user(User::ROLE_STUDENT, 'Иван Петров');

        $this->single = $this->room('Английский язык', [$this->alina]);
        $this->group = $this->room('ЕГЭ-2027', [$this->alina, $this->ivan]);
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

    private function room(string $name, array $students, ?User $teacher = null): Room
    {
        $room = Room::create([
            'user_id' => ($teacher ?? $this->teacher)->id,
            'name' => $name,
            'meeting_id' => 'm-' . uniqid(),
            'moderator_pw' => 'mod',
            'attendee_pw' => 'att',
        ]);
        $room->participants()->attach(collect($students)->pluck('id'));

        return $room;
    }

    private function message(Room $room, User $from, string $text, array $extra = []): Message
    {
        return Message::create(['room_id' => $room->id, 'user_id' => $from->id, 'content' => $text] + $extra);
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.messages'))->assertRedirect();

        $this->actingAs($this->alina)->get(route('cabinet.teacher.messages'))->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->teacher)->get(route('cabinet.student.messages'))->assertRedirect(route('cabinet.teacher.today'));

        $this->actingAs($this->teacher)->get(route('cabinet.teacher.messages'))
            ->assertOk()
            ->assertSee('Сообщения')
            ->assertSee('Поддержка Serdal')
            ->assertSee('Алина Смирнова')
            ->assertSee('ЕГЭ-2027')
            ->assertSee('Выберите диалог');

        $this->actingAs($this->ivan)->get(route('cabinet.student.messages'))
            ->assertOk()
            ->assertSee('ЕГЭ-2027')
            ->assertDontSee('Английский язык');
    }

    public function test_dialog_list_shows_previews_unread_and_marks_read_on_open(): void
    {
        $this->message($this->single, $this->alina, 'Спасибо, эссе пришлю вечером');
        $this->message($this->group, $this->ivan, 'А ответы будут до субботы?');
        $this->message($this->group, $this->teacher, 'Напоминаю про пробник');

        $this->assertSame(2, app(MessengerService::class)->unreadCount($this->teacher));

        $c = Livewire::actingAs($this->teacher)->test(Messages::class)
            ->assertSee('Спасибо, эссе пришлю вечером')
            ->assertSee('Вы: Напоминаю про пробник');

        $c->call('open', 'room-' . $this->single->id)
            ->assertSet('room', $this->single->id)
            ->assertSee('Карточка ученика')
            ->assertSee('Английский язык');

        $this->assertSame(1, app(MessengerService::class)->unreadCount($this->teacher)); // группу ещё не открывали
        $this->assertSame(1, app(MessengerService::class)->unreadCount($this->ivan)); // учительское сообщение в группе
    }

    public function test_group_messages_show_author_and_read_marks(): void
    {
        $this->message($this->group, $this->ivan, 'А ответы будут до субботы?');
        $this->message($this->group, $this->teacher, 'Будут в пятницу', ['read_at' => now()]);

        Livewire::withQueryParams(['room' => $this->group->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSee('Иван Петров')
            ->assertSee('Сегодня')
            ->assertSee('прочитано');
    }

    public function test_teacher_sends_text_and_file_participants_are_notified(): void
    {
        Livewire::withQueryParams(['room' => $this->group->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->set('picked', [UploadedFile::fake()->create('Вариант 12.pdf', 300, 'application/pdf')])
            ->assertHasNoErrors()
            ->assertSee('Вариант 12.pdf')
            ->set('draft', "  Решите задачи 1–12\nдо субботы  ")
            ->call('send')
            ->assertSet('draft', '')
            ->assertSet('files', [])
            ->assertSee('Решите задачи 1–12');

        $m = Message::firstOrFail();
        $this->assertSame($this->teacher->id, $m->user_id);
        $this->assertSame("Решите задачи 1–12\nдо субботы", $m->content);
        $this->assertSame('Вариант 12.pdf', $m->attachments[0]['name']);
        Storage::disk('s3')->assertExists($m->attachments[0]['path']);

        Queue::assertPushed(SendUnreadMessageNotification::class, 2);
        Queue::assertPushed(SendUnreadMessageNotification::class, fn ($job) => $job->recipient->id === $this->ivan->id);
        Queue::assertNotPushed(SendUnreadMessageNotification::class, fn ($job) => $job->recipient->id === $this->teacher->id);
    }

    public function test_student_writes_to_teacher_and_empty_message_is_not_sent(): void
    {
        $c = Livewire::withQueryParams(['room' => $this->single->id])
            ->actingAs($this->alina)
            ->test(Messages::class)
            ->assertSee('Мария Соколова')
            ->call('send');
        $this->assertSame(0, Message::count());

        $c->set('draft', 'Можно перенести субботу на 12:00?')->call('send');
        $this->assertSame(1, Message::count());
        Queue::assertPushed(SendUnreadMessageNotification::class, fn ($job) => $job->recipient->id === $this->teacher->id);
    }

    public function test_foreign_room_is_not_opened(): void
    {
        $stranger = $this->user(User::ROLE_STUDENT);
        $foreign = $this->room('Чужое занятие', [$stranger], $this->user(User::ROLE_TUTOR));

        // Чужой чат из ссылки просто не открывается
        Livewire::withQueryParams(['room' => $foreign->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSet('room', null)
            ->assertDontSee('Чужое занятие');

        // Ученик не видит занятие, в котором не участвует
        Livewire::actingAs($this->ivan)->test(Messages::class)
            ->call('open', 'room-' . $this->single->id)
            ->assertNotFound();
    }

    public function test_edit_and_delete_rules(): void
    {
        $own = $this->message($this->group, $this->ivan, 'Опечтка');
        $other = $this->message($this->group, $this->alina, 'Сообщение Алины');

        $c = Livewire::withQueryParams(['room' => $this->group->id])->actingAs($this->ivan)->test(Messages::class)
            ->call('edit', $own->id)
            ->assertSet('draft', 'Опечтка')
            ->set('draft', 'Опечатка')
            ->call('send');
        $this->assertSame('Опечатка', $own->fresh()->content);
        $this->assertSame(2, Message::count());

        // Чужое сообщение ученик не меняет и не удаляет
        $c->call('edit', $other->id)->assertForbidden();
        Livewire::withQueryParams(['room' => $this->group->id])->actingAs($this->ivan)->test(Messages::class)
            ->call('confirmDelete', $other->id)->assertForbidden();

        // Учитель удаляет любое сообщение в своём занятии
        Livewire::withQueryParams(['room' => $this->group->id])->actingAs($this->teacher)->test(Messages::class)
            ->call('confirmDelete', $other->id)
            ->assertSee('Удалить сообщение?')
            ->call('delete');
        $this->assertNull($other->fresh());

        // …но не в чужом: сообщение из чужого чата не находится
        $foreign = $this->room('Чужое', [$this->ivan], $this->user(User::ROLE_TUTOR));
        $msg = $this->message($foreign, $this->ivan, 'Не ваше');
        $this->assertFalse(app(MessengerService::class)->canDelete($this->teacher, $msg));
        Livewire::withQueryParams(['room' => $this->group->id])->actingAs($this->teacher)->test(Messages::class)
            ->call('confirmDelete', $msg->id)
            ->assertNotFound();
    }

    public function test_archived_room_is_read_only(): void
    {
        $this->message($this->single, $this->alina, 'Спасибо за занятия!');
        $this->single->delete();

        Livewire::withQueryParams(['room' => $this->single->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSee('Архив')
            ->assertSee('писать сюда больше нельзя')
            ->set('draft', 'Ещё одно')
            ->call('send')
            ->assertForbidden();
    }

    public function test_support_chat(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $c = Livewire::withQueryParams(['support' => 1])
            ->actingAs($this->alina)
            ->test(Messages::class)
            ->assertSee('Вопросы о кабинете, оплате и занятиях')
            ->set('draft', 'Не вижу запись занятия')
            ->call('send');

        $chat = SupportChat::where('user_id', $this->alina->id)->firstOrFail();
        $this->assertSame(1, $chat->messages()->count());
        Queue::assertPushed(SendUnreadSupportMessageNotification::class, fn ($job) => $job->recipient->id === $admin->id);
        Queue::assertPushed(SendSupportMessageTelegramNotification::class);

        SupportMessage::create(['support_chat_id' => $chat->id, 'user_id' => $admin->id, 'content' => 'Запись появится через час']);
        $this->assertSame(1, app(MessengerService::class)->unreadCount($this->alina));

        $c->call('incoming')->assertSee('Запись появится через час');
        $this->assertSame(0, app(MessengerService::class)->unreadCount($this->alina));
    }

    public function test_search_filters_dialogs(): void
    {
        Livewire::actingAs($this->teacher)->test(Messages::class)
            ->set('q', 'егэ')
            ->assertSee('ЕГЭ-2027')
            ->assertDontSee('Алина Смирнова')
            ->set('q', 'нет такого')
            ->assertSee('Никого не нашли');
    }

    public function test_links_lead_to_new_screen(): void
    {
        $this->assertSame(route('cabinet.teacher.messages', ['room' => 5]), MessengerService::url($this->teacher, 5));
        $this->assertSame(route('cabinet.student.messages', ['support' => 1]), MessengerService::url($this->alina, support: true));
    }

    public function test_teacher_writes_to_student_without_lessons(): void
    {
        $oleg = $this->user(User::ROLE_STUDENT, 'Олег Кузнецов');
        $this->teacher->students()->attach($oleg);

        // Общих занятий нет — ссылка ведёт в личный чат, он создаётся при открытии
        $url = app(TeacherStudentsService::class)->chatUrl($this->teacher, $oleg->id);
        $this->assertSame(route('cabinet.teacher.messages', ['with' => $oleg->id]), $url);

        Livewire::withQueryParams(['with' => $oleg->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSet('with', null)
            ->assertSee('Олег Кузнецов')
            ->assertSee('Карточка ученика')
            ->set('draft', 'Олег, добрый день! Когда удобно начать?')
            ->call('send');

        $chat = PersonalChat::firstOrFail();
        $this->assertSame([$this->teacher->id, $oleg->id], [$chat->teacher_id, $chat->student_id]);
        $this->assertSame($chat->id, Message::firstOrFail()->personal_chat_id);
        Queue::assertPushed(SendUnreadMessageNotification::class, fn ($job) => $job->recipient->id === $oleg->id);

        // Повторная ссылка ведёт в тот же чат; у ученика он в списке, ответ доходит учителю
        $this->assertSame(route('cabinet.teacher.messages', ['personal' => $chat->id]), app(TeacherStudentsService::class)->chatUrl($this->teacher, $oleg->id));
        $this->assertSame(1, app(MessengerService::class)->unreadCount($oleg));

        Livewire::actingAs($oleg)->test(Messages::class)
            ->assertSee('Мария Соколова')
            ->assertSee('Олег, добрый день!')
            ->call('open', 'personal-' . $chat->id)
            ->set('draft', 'Давайте с понедельника')
            ->call('send');

        $this->assertSame(0, app(MessengerService::class)->unreadCount($oleg));
        $this->assertSame(1, app(MessengerService::class)->unreadCount($this->teacher));
        Queue::assertPushed(SendUnreadMessageNotification::class, fn ($job) => $job->recipient->id === $this->teacher->id);
    }

    public function test_student_sees_teacher_without_lessons_and_writes_first(): void
    {
        $oleg = $this->user(User::ROLE_STUDENT, 'Олег Кузнецов');
        $this->teacher->students()->attach($oleg);

        $this->assertSame(
            route('cabinet.student.messages', ['with' => $this->teacher->id]),
            app(StudentTeachersService::class)->chatUrl($oleg->id, $this->teacher->id),
        );

        Livewire::actingAs($oleg)->test(Messages::class)
            ->assertSee('Мария Соколова')
            ->call('open', 'with-' . $this->teacher->id)
            ->assertSet('personal', fn ($id) => $id !== null)
            ->set('draft', 'Здравствуйте! Я по поводу занятий')
            ->call('send');

        $this->assertSame(1, PersonalChat::where('teacher_id', $this->teacher->id)->where('student_id', $oleg->id)->count());
        $this->assertSame(1, app(MessengerService::class)->unreadCount($this->teacher));
    }

    public function test_write_link_prefers_individual_lesson_chat(): void
    {
        $this->teacher->students()->attach([$this->alina->id, $this->ivan->id]);

        // У Алины есть индивидуальное занятие — пишем туда, у Ивана только группа — в личный чат
        $this->assertSame(route('cabinet.teacher.messages', ['room' => $this->single->id]), app(TeacherStudentsService::class)->chatUrl($this->teacher, $this->alina->id));
        $this->assertSame(route('cabinet.teacher.messages', ['with' => $this->ivan->id]), app(TeacherStudentsService::class)->chatUrl($this->teacher, $this->ivan->id));
        $this->assertSame(route('cabinet.student.messages', ['room' => $this->single->id]), app(StudentTeachersService::class)->chatUrl($this->alina->id, $this->teacher->id));
    }

    public function test_personal_chat_needs_teacher_student_link(): void
    {
        $stranger = $this->user(User::ROLE_STUDENT, 'Пётр Чужой');

        // Не ученик учителя — чат не создаётся
        Livewire::withQueryParams(['with' => $stranger->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSet('personal', null);
        $this->assertSame(0, PersonalChat::count());

        // Чужой личный чат не открывается
        $otherTeacher = $this->user(User::ROLE_TUTOR);
        $foreign = PersonalChat::create(['teacher_id' => $otherTeacher->id, 'student_id' => $stranger->id]);
        Livewire::withQueryParams(['personal' => $foreign->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSet('personal', null);

        // Ученика убрали из списка — переписка остаётся для чтения
        $this->teacher->students()->attach($this->ivan);
        $chat = PersonalChat::create(['teacher_id' => $this->teacher->id, 'student_id' => $this->ivan->id]);
        Message::create(['personal_chat_id' => $chat->id, 'user_id' => $this->ivan->id, 'content' => 'Спасибо за занятия']);
        $this->teacher->students()->detach($this->ivan);

        Livewire::withQueryParams(['personal' => $chat->id])
            ->actingAs($this->teacher)
            ->test(Messages::class)
            ->assertSee('Спасибо за занятия')
            ->assertSee('Архив')
            ->set('draft', 'Ещё одно')
            ->call('send')
            ->assertForbidden();
    }
}
