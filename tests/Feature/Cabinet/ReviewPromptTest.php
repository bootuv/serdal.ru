<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Home;
use App\Livewire\Cabinet\Teacher\Reviews;
use App\Livewire\Cabinet\Teacher\Student as TeacherStudent;
use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\ReviewInvite;
use App\Services\PlatformReviewService;
use App\Services\ReviewPromptService;
use App\Services\StudentTeachersService;
use App\Services\TutorCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Приглашения оставить отзыв: карточка на главной ученика, просьба учителя, уведомление, порядок каталога. */
class ReviewPromptTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fixNow();
    }

    /** Учитель с учеником и $lessons занятиями. */
    private function pair(int $lessons): array
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $student = $this->studentOf($teacher);
        $this->lessons($teacher, $student, $lessons);
        $this->fakeJsonLessonQueries();

        return [$teacher, $student];
    }

    private function lessons(User $teacher, User $student, int $count): void
    {
        $room = Room::where('user_id', $teacher->id)->first() ?? $this->room($teacher, [$student]);
        for ($i = 0; $i < $count; $i++) {
            MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
                'status' => 'completed', 'started_at' => now()->subDays($i + 1), 'ended_at' => now()->subDays($i + 1)->addHour(),
                'analytics_data' => ['participants' => [['user_id' => (string) $teacher->id], ['user_id' => (string) $student->id]]]]);
        }
    }

    /** SQLite не умеет whereJsonContains по объектам в analytics_data — как в StudentHomeTest. */
    private function fakeJsonLessonQueries(): void
    {
        $this->partialMock(StudentTeachersService::class, function ($mock) {
            $mock->shouldReceive('lessonsWithCurrentTeacher')->andReturnUsing(fn (int $studentId, User $teacher) => MeetingSession::query()
                ->whereHas('room', fn ($q) => $q->where('user_id', $teacher->id)
                    ->whereHas('participants', fn ($p) => $p->where('users.id', $studentId)))->get());
            $mock->shouldReceive('formerTeachers')->andReturnUsing(fn (int $studentId) => User::query()->whereRaw('0 = 1'));
        });
    }

    public function test_card_appears_after_three_lessons_and_star_opens_review(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair(2);
        Livewire::actingAs($student)->test(Home::class)->assertDontSee('Как вам занятия?');

        $this->lessons($teacher, $student, 1);
        Livewire::actingAs($student)->test(Home::class)
            ->assertSee('Как вам занятия?')
            ->set('promptRating', 4)
            ->assertSet('reviewTeacherId', $teacher->id)
            ->assertSet('rating', 4)
            ->assertSee('Отзыв об учителе')
            ->set('reviewText', 'Разобрались с логарифмами')
            ->call('saveReview')
            ->assertHasNoErrors()
            ->assertDontSee('Как вам занятия?');
    }

    public function test_news_and_rate_cards_both_keep_blue_background(): void
    {
        $this->assertStringContainsString('bg-news', \Illuminate\Support\Facades\Blade::render('<x-ui.card news>Новость</x-ui.card>'));
        $this->assertStringContainsString('bg-news', \Illuminate\Support\Facades\Blade::render('<x-ui.card rate>Отзыв</x-ui.card>'));
        $this->assertStringNotContainsString('bg-news', \Illuminate\Support\Facades\Blade::render('<x-ui.card>Обычная</x-ui.card>'));
    }

    public function test_later_hides_card_for_five_lessons_and_twice_for_good(): void
    {
        [$teacher, $student] = $this->pair(3);

        Livewire::actingAs($student)->test(Home::class)->call('dismissReviewPrompt')->assertDontSee('Как вам занятия?');

        $this->lessons($teacher, $student, 4);
        Livewire::actingAs($student)->test(Home::class)->assertDontSee('Как вам занятия?');

        $this->lessons($teacher, $student, 1);
        Livewire::actingAs($student)->test(Home::class)->assertSee('Как вам занятия?')->call('dismissReviewPrompt');

        $this->lessons($teacher, $student, 10);
        Livewire::actingAs($student)->test(Home::class)->assertDontSee('Как вам занятия?')->assertSee('Оставить отзыв');

        // Просьба учителя показывает карточку снова
        $this->travel(1)->minutes();
        Notification::fake();
        $this->assertTrue(app(ReviewPromptService::class)->request($teacher, $student));
        Livewire::actingAs($student)->test(Home::class)->assertSee('Как вам занятия?')->assertSee('Просит оставить отзыв');
    }

    public function test_link_from_notification_opens_review_window(): void
    {
        [$teacher, $student] = $this->pair(1);

        Livewire::withQueryParams(['review' => $teacher->id])->actingAs($student)->test(Home::class)
            ->assertSet('reviewTeacherId', $teacher->id);

        // Чужой учитель — просто главная, без ошибки
        $other = $this->user(User::ROLE_TUTOR);
        Livewire::withQueryParams(['review' => $other->id])->actingAs($student)->test(Home::class)
            ->assertOk()->assertSet('reviewTeacherId', null);
    }

    public function test_teacher_asks_one_student_not_more_than_monthly(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair(1);

        Livewire::actingAs($teacher)->test(TeacherStudent::class, ['student' => $student])
            ->assertSee('Отзыва пока нет')
            ->call('requestReview')
            ->assertDispatched('toast', message: 'Отправили просьбу об отзыве: ' . $student->name)
            ->assertSee('ждём отзыв')
            ->assertDontSee('Попросить отзыв');

        Notification::assertSentToTimes($student, ReviewInvite::class, 1);
        $this->assertFalse(app(ReviewPromptService::class)->request($teacher, $student), 'второй раз в том же месяце');

        $this->travel(31)->days();
        $this->assertTrue(app(ReviewPromptService::class)->request($teacher, $student));

        // Отзыв оставлен — просить больше нельзя, в карточке видна оценка
        Review::create(['user_id' => $student->id, 'teacher_id' => $teacher->id, 'rating' => 5, 'text' => 'Спасибо']);
        Livewire::actingAs($teacher)->test(TeacherStudent::class, ['student' => $student])->assertDontSee('Попросить отзыв');
    }

    public function test_teacher_asks_all_students_without_review(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair(1);
        $this->studentOf($teacher, 'Без занятий'); // занятий не было — не просим

        Livewire::actingAs($teacher)->test(Reviews::class)
            ->call('requestAll')
            ->assertDispatched('toast', message: 'Попросили 1 ученика оставить отзыв')
            ->call('requestAll')
            ->assertDispatched('toast', message: 'Просить пока некого: у учеников без отзыва ещё не было занятий или их уже просили в этом месяце');

        Notification::assertSentToTimes($student, ReviewInvite::class, 1);
    }

    public function test_notification_after_third_lesson_is_sent_once(): void
    {
        Notification::fake();
        [$teacher, $student] = $this->pair(2);

        $this->artisan('reviews:invite')->assertSuccessful();
        Notification::assertNothingSent();

        $this->lessons($teacher, $student, 1);
        $this->artisan('reviews:invite')->assertSuccessful();
        $this->artisan('reviews:invite')->assertSuccessful();

        Notification::assertSentToTimes($student, ReviewInvite::class, 1);
        $this->assertNotNull(DB::table('teacher_student')->where('student_id', $student->id)->value('review_prompt_notified_at'));
    }

    public function test_good_reviews_raise_teacher_in_catalog(): void
    {
        $make = function (string $name, array $ratings) {
            $teacher = $this->user(User::ROLE_TUTOR, ['name' => $name]);
            $room = $this->room($teacher);
            MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id,
                'status' => 'completed', 'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHour()]);
            foreach ($ratings as $rating) {
                Review::create(['user_id' => $this->user(User::ROLE_STUDENT)->id, 'teacher_id' => $teacher->id, 'rating' => $rating, 'text' => 'Отзыв']);
            }

            return $teacher;
        };

        $plain = $make('Без отзывов', []);
        $bad = $make('Плохие отзывы', [2, 3]);
        $good = $make('Хорошие отзывы', [5, 5]);

        $order = app(TutorCatalogService::class)->tutors()->pluck('id')->all();
        $this->assertSame($good->id, $order[0]);
        $this->assertSame([$plain->id, $bad->id], array_slice($order, 1), 'плохие оценки не поднимают');
    }

    public function test_platform_prompt_returns_after_snooze_and_stops_after_three(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $teacher->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
        $room = $this->room($teacher);
        for ($i = 0; $i < 5; $i++) {
            MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'status' => 'completed', 'started_at' => now()->subDay()]);
        }
        $service = app(PlatformReviewService::class);

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($service->shouldPrompt($teacher->fresh()), 'показ ' . ($i + 1));
            $service->dismissPrompt($teacher->fresh());
            $this->assertFalse($service->shouldPrompt($teacher->fresh()));
            $this->travel(31)->days();
        }

        $this->assertFalse($service->shouldPrompt($teacher->fresh()), 'после третьего «Не сейчас» не спрашиваем');
    }
}
