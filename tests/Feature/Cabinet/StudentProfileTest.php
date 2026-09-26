<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Profile;
use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\StudentLeftReview;
use App\Notifications\StudentUpdatedReview;
use App\Services\StudentTeachersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Профиль ученика в новом кабинете (/cabinet/student/profile). */
class StudentProfileTest extends TestCase
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

    public function test_guest_is_redirected_and_teacher_is_forbidden(): void
    {
        $this->get(route('cabinet.student.profile'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_TUTOR))
            ->get(route('cabinet.student.profile'))
            ->assertRedirect(route('cabinet.teacher.today'));
    }

    public function test_profile_page_renders_form_and_logout(): void
    {
        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Алина Смирнова', 'grade' => [10]]);

        $this->actingAs($student)
            ->get(route('cabinet.student.profile'))
            ->assertOk()
            ->assertSee('Личные данные')
            ->assertSee('Алина Смирнова')
            ->assertSee('Уведомления')
            ->assertSee(route('logout'), false)
            ->assertSee('Выйти');

        Livewire::actingAs($student)->test(Profile::class)->assertSet('grade', '10');
    }

    public function test_student_saves_profile_password_and_photo(): void
    {
        Storage::fake('s3');
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('last_name', 'Смирнова')
            ->set('first_name', 'Алина')
            ->set('email', 'alina@example.com')
            ->set('phone', '+7 916 555-12-34')
            ->set('grade', '9')
            ->set('password', 'new-secret')
            ->set('photo', UploadedFile::fake()->image('me.jpg', 1200, 1200))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('password', '')
            ->assertSee('Изменения сохранены');

        $student->refresh();
        $this->assertSame('Смирнова Алина', $student->name);
        $this->assertSame('Алина', $student->first_name);
        $this->assertSame('alina@example.com', $student->email);
        $this->assertSame([9], $student->grade);
        $this->assertTrue(Hash::check('new-secret', $student->password));
        $this->assertStringStartsWith('avatars/' . $student->id . '/', $student->avatar);
        Storage::disk('s3')->assertExists($student->avatar);
    }

    public function test_profile_validation(): void
    {
        $this->user(User::ROLE_STUDENT, ['email' => 'taken@example.com']);
        $student = $this->user(User::ROLE_STUDENT);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('first_name', '')
            ->set('email', 'taken@example.com')
            ->set('phone', 'позвоните мне')
            ->set('grade', '42')
            ->call('save')
            ->assertHasErrors(['first_name', 'email', 'phone', 'grade'])
            ->assertSet('saved', false);

        Livewire::actingAs($student)
            ->test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['photo']);
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
            ->test(Profile::class)
            ->assertSee('Иван Орлов')
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

        $component = Livewire::actingAs($student)->test(Profile::class)
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

        $component = Livewire::actingAs($student)->test(Profile::class)->call('openReview', $teacher->id);

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
            ->test(Profile::class)
            ->assertSee('Отзыв — после первого занятия')
            ->assertSee('Дмитрий Зайцев')
            ->assertSee('Отзыв скрыт модератором');

        $component->call('openReview', $newTeacher->id)->assertForbidden();
        Livewire::actingAs($student)->test(Profile::class)->call('openReview', $rejectedTeacher->id)->assertForbidden();
        Livewire::actingAs($student)->test(Profile::class)->call('openReview', $stranger->id)->assertForbidden();
    }
}
