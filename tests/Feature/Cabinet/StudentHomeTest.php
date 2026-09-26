<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Home;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\StudentLeftReview;
use App\Notifications\StudentUpdatedReview;
use App\Services\StudentTeachersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Главная ученика в новом кабинете (/cabinet/student). */
class StudentHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.student.home'))->assertRedirect();
    }

    public function test_teacher_cannot_open_student_home(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.home'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_student_without_lessons_sees_empty_state(): void
    {
        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertSee('Учитель ещё не назначил время')
            ->assertDontSee('Ваш учитель')
            ->assertSee('Первые шаги')
            ->assertSeeText('0 из 2')
            ->assertDontSee('К оплате');
    }

    public function test_empty_home_shows_teacher_and_first_steps(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->update(['name' => 'Мария Соколова', 'telegram' => 'maria_english']);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        // Класс указан — профиль заполнен
        $student->update(['grade' => [7]]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertSee('Мария Соколова')
            ->assertSee('Ваш учитель')
            ->assertSee('Написать учителю')
            ->assertSee('@maria_english')
            ->assertSee('https://t.me/maria_english', false)
            ->assertSee('Первые шаги')
            ->assertSeeText('1 из 2')
            ->assertSee('Включите уведомления')
            ->assertSee('Заполните профиль')
            ->assertSee(route('cabinet.student.profile'), false)
            // Занятий не было — карточка «Учителя» повторила бы фокус-блок
            ->assertDontSee('Отзыв — после первого занятия');
    }

    public function test_first_steps_disappear_when_done(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $student->update(['grade' => [7]]);
        $student->updatePushSubscription('https://push.example/' . uniqid(), 'key', 'token');

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Занятий пока нет')
            ->assertDontSee('Первые шаги');
    }

    private function upcoming(User $student, array $attrs): Room
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);

        return $room;
    }

    public function test_join_is_hidden_until_15_minutes_before_start(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->upcoming($student, ['next_start' => now()->addMinutes(20)]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertDontSee(route('rooms.connect', $room), false)
            ->assertSee('Вход откроется в ' . now()->addMinutes(5)->format('H:i'))
            ->assertSee(route('cabinet.student.lesson', $room), false);

        $room->update(['next_start' => now()->addMinutes(10)]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false);
    }

    public function test_student_sees_running_lesson_and_unpaid_lessons(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);

        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'is_running' => true,
            'next_start' => now()->subMinutes(5),
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);

        PaymentRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID,
            'due_date' => now()->addDays(2),
        ]);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Английский язык')
            ->assertSee('идёт сейчас')
            ->assertSee('Войти в класс')
            ->assertSee(route('rooms.connect', $room), false)
            ->assertSee('К оплате')
            ->assertSee('1 занятие');
    }

    public function test_debt_card_warns_and_week_list_marks_closed_entry(): void
    {
        $this->travelTo(now()->setTime(12, 0)); // занятие «через 2 часа» должно остаться сегодняшним
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->update(['name' => 'Мария Соколова']);
        $student = $this->user(User::ROLE_STUDENT);

        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'next_start' => now()->addHours(2),
            'duration' => 60,
        ]);
        $room->participants()->attach($student->id);
        $later = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Разговорная практика',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
            'next_start' => now()->addDays(2),
            'duration' => 60,
        ]);
        $later->participants()->attach($student->id);

        // Долг просрочен, после срока — 2 занятия: следующее пройдёт, потом вход закроется
        $session = fn ($daysAgo, $price) => \App\Models\MeetingSession::create([
            'user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'status' => 'completed',
            'started_at' => now()->subDays($daysAgo)->subHour(), 'ended_at' => now()->subDays($daysAgo),
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => true, 'price' => $price]]],
        ]);
        $first = $session(12, 1500);
        PaymentRecord::create(['teacher_id' => $teacher->id, 'student_id' => $student->id, 'type' => 'per_lesson',
            'status' => PaymentRecord::STATUS_UNPAID, 'meeting_session_id' => $first->id, 'due_date' => now()->subDays(10)]);
        $session(6, 1500);
        $session(3, 1500);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Оплатите занятия')
            ->assertSee('Просрочено')
            ->assertSee('Мария Соколова · 1 занятие')
            ->assertSee('1 500 ₽')
            ->assertSee('Сегодняшнее занятие пройдёт как обычно')
            ->assertSee('Сообщить об оплате')
            ->assertSee(route('cabinet.student.payments', ['report' => $teacher->id]), false)
            ->assertSee('Написать учителю')
            ->assertDontSee('вход закрыт');

        // Третье занятие после срока — вход закрыт, в списке недели пометка
        $session(1, 1500);

        $this->actingAs($student)
            ->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('Вход на занятия закрыт')
            ->assertSee('Не оплачено')
            ->assertSee('вход закрыт')
            ->assertSee('Вход закрыт до оплаты')
            ->assertDontSee('Откроется после оплаты');
    }

    /** Занятие учителя, на котором были ученик и учитель (как в analytics_data от BBB). */
    private function lessonWith(User $teacher, User $student): void
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Математика',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach($student->id);

        MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'status' => 'completed',
            'started_at' => now()->subDays(3),
            'ended_at' => now()->subDays(3)->addHour(),
            'analytics_data' => ['participants' => [
                ['user_id' => (string) $teacher->id],
                ['user_id' => (string) $student->id],
            ]],
        ]);
    }

    /**
     * SQLite не умеет whereJsonContains по объектам в analytics_data — подменяем только эти два запроса
     * (логика перенесена из виджетов старого кабинета без изменений, в MySQL она работает).
     */
    private function fakeJsonLessonQueries(): void
    {
        $this->partialMock(StudentTeachersService::class, function ($mock) {
            $mock->shouldReceive('lessonsWithCurrentTeacher')->andReturnUsing(fn (int $studentId, User $teacher) => MeetingSession::query()
                ->whereHas('room', fn ($q) => $q->where('user_id', $teacher->id))->get());
            $mock->shouldReceive('formerTeachers')->andReturnUsing(fn (int $studentId) => User::query()
                ->whereIn('id', Room::whereHas('sessions')->pluck('user_id'))
                ->whereNotIn('id', \DB::table('teacher_student')->where('student_id', $studentId)->pluck('teacher_id')));
        });
    }

    public function test_student_leaves_and_edits_review(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        $this->lessonWith($teacher, $student);
        $this->fakeJsonLessonQueries();

        $component = Livewire::actingAs($student)
            ->test(Home::class)
            ->assertSee('Иван Орлов')
            ->assertSee(route('tutors.show', ['username' => $teacher->username]), false)
            ->assertSee('>Написать учителю</a>', false)
            ->assertDontSee('>Написать</a>', false)
            ->assertSee('Оставить отзыв')
            ->call('openReview', $teacher->id)
            ->assertSee('Отзыв об учителе')
            ->set('rating', 4)
            ->set('reviewText', '')
            ->call('saveReview')
            ->assertHasErrors(['reviewText'])
            ->set('reviewText', 'Разобрались с логарифмами')
            ->call('saveReview')
            ->assertHasNoErrors()
            ->assertSet('reviewTeacherId', null)
            ->assertDispatched('toast', message: 'Спасибо! Отзыв опубликован')
            ->assertSee('Изменить отзыв');

        $review = Review::where('user_id', $student->id)->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(4, (int) $review->rating);
        Notification::assertSentTo($teacher, StudentLeftReview::class);

        // Изменение — тот же отзыв (updateOrCreate), без повторного уведомления
        $component->call('openReview', $teacher->id)
            ->assertSet('rating', 4)
            ->assertSet('reviewText', 'Разобрались с логарифмами')
            ->assertSee('Ваш отзыв')
            ->set('rating', 5)
            ->call('saveReview')
            ->assertDispatched('toast', message: 'Отзыв обновлён');

        $this->assertSame(1, Review::where('user_id', $student->id)->count());
        $this->assertSame(5, (int) $review->fresh()->rating);
        Notification::assertSentToTimes($teacher, StudentLeftReview::class, 1);
    }

    public function test_edited_review_is_new_again_and_teacher_is_notified(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        $this->lessonWith($teacher, $student);
        $this->fakeJsonLessonQueries();

        // Учитель прочитал отзыв и пожаловался на него
        $review = Review::create([
            'user_id' => $student->id, 'teacher_id' => $teacher->id, 'rating' => 2, 'text' => 'Переносили три раза',
            'teacher_read_at' => now()->subDay(), 'is_reported' => true, 'report_reason' => 'rude', 'reported_at' => now()->subDay(),
        ]);

        $component = Livewire::actingAs($student)->test(Home::class)
            ->call('openReview', $teacher->id)
            ->call('saveReview') // ничего не изменилось — учителя не тревожим
            ->assertHasNoErrors();
        $this->assertNotNull($review->fresh()->teacher_read_at);
        Notification::assertNotSentTo($teacher, StudentUpdatedReview::class);

        $component->call('openReview', $teacher->id)
            ->set('rating', 4)
            ->set('reviewText', 'Переносили, но потом наладилось')
            ->call('saveReview')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Отзыв обновлён');

        $review->refresh();
        $this->assertNull($review->teacher_read_at, 'Изменённый отзыв снова в «Новых»');
        $this->assertFalse((bool) $review->is_reported, 'Жалоба была на прежний текст');
        $this->assertNull($review->report_reason);
        Notification::assertSentTo($teacher, StudentUpdatedReview::class);
        Notification::assertNotSentTo($teacher, StudentLeftReview::class);
    }

    public function test_review_rejects_contacts_and_long_text(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($student->id);
        $this->lessonWith($teacher, $student);
        $this->fakeJsonLessonQueries();

        $component = Livewire::actingAs($student)->test(Home::class)->call('openReview', $teacher->id);

        foreach ([
            'Пишите мне: +7 (900) 123-45-67' => 'Уберите номер телефона',
            'Все материалы на https://example.com' => 'Уберите ссылку',
            'Мой канал t.me/english_club' => 'Уберите ссылку',
            'Пишите в телеграм @english_club' => 'Уберите почту или @имя',
        ] as $text => $error) {
            $component->set('reviewText', $text)->call('saveReview')->assertHasErrors(['reviewText'])->assertSee($error);
        }

        $component->set('reviewText', str_repeat('а', Review::MAX_TEXT + 1))->call('saveReview')->assertHasErrors(['reviewText' => 'max']);
        $this->assertSame(0, Review::count());

        // Цифры в тексте — не телефон
        $component->set('reviewText', 'За 2 месяца пробник с 54 на 82 балла, занимались с 10:00 до 12:00')->call('saveReview')->assertHasNoErrors();
        $this->assertSame(1, Review::count());
    }

    public function test_review_rules_no_lessons_rejected_and_foreign_teacher(): void
    {
        $newTeacher = $this->user(User::ROLE_TUTOR, ['name' => 'Екатерина Белова']);
        $rejectedTeacher = $this->user(User::ROLE_TUTOR, ['name' => 'Дмитрий Зайцев']);
        $stranger = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT);
        $newTeacher->students()->attach($student->id);

        // Бывший учитель: занятия были, связи больше нет, отзыв отклонён модератором
        $this->lessonWith($rejectedTeacher, $student);
        Review::create(['user_id' => $student->id, 'teacher_id' => $rejectedTeacher->id, 'rating' => 1, 'text' => 'x', 'is_rejected' => true]);
        $this->fakeJsonLessonQueries();

        $component = Livewire::actingAs($student)
            ->test(Home::class)
            ->assertSee('Отзыв — после первого занятия')
            ->assertSee('Дмитрий Зайцев')
            ->assertSee('Отзыв скрыт модератором');

        $component->call('openReview', $newTeacher->id)->assertForbidden();
        Livewire::actingAs($student)->test(Home::class)->call('openReview', $rejectedTeacher->id)->assertForbidden();
        Livewire::actingAs($student)->test(Home::class)->call('openReview', $stranger->id)->assertForbidden();
    }
}
