<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Notifications\CabinetNotification;
use App\Notifications\HomeworkDeadlineSoon;
use App\Notifications\HomeworkSubmitted;
use App\Notifications\LessonsRunningOut;
use App\Notifications\SubscriptionAutoRenewNotice;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\SubscriptionPaid;
use App\Notifications\TeacherUpdatedSchedule;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Логика уведомлений кабинета: каналы, очередь, письма, тексты и напоминания. */
class NotificationsLogicTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixNow(); // 2026-09-24 12:00
    }

    public function test_all_cabinet_notifications_are_queued_after_commit(): void
    {
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            $class = 'App\\Notifications\\' . basename($file, '.php');
            if (in_array($class, [CabinetNotification::class, \App\Notifications\EmailVerificationCode::class], true)) {
                continue;
            }
            $this->assertTrue(is_subclass_of($class, CabinetNotification::class), $class . ' — уведомление кабинета');
            $this->assertTrue(is_subclass_of($class, ShouldQueueAfterCommit::class), $class . ' — в очередь после коммита');
        }
    }

    public function test_channels_push_and_mail(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['first_name' => 'Мария']);

        // Без пуш-подписки и без письма — кабинет и реалтайм
        $n = new TeacherUpdatedSchedule($teacher);
        $this->assertSame(['database', 'broadcast'], $n->via($teacher));

        // Деньги — ещё и письмом
        $paid = new SubscriptionPaid('Базовый', 1490, Carbon::parse('2026-10-24'));
        $this->assertContains('mail', $paid->via($teacher));

        // Пуш — если включён на каком-то устройстве
        $teacher->updatePushSubscription('https://push.example/1', 'key', 'token');
        $this->assertContains(WebPushChannel::class, $n->via($teacher->fresh()));

        $mail = $paid->toMail($teacher);
        $this->assertSame('Тариф оплачен — Serdal', $mail->subject);
        $this->assertSame('Здравствуйте, Мария!', $mail->greeting);
        $this->assertSame('Тариф и платежи', $mail->actionText);
        $this->assertSame(route('cabinet.teacher.subscription'), $mail->actionUrl);
    }

    public function test_texts_use_human_dates_and_neutral_wording(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $texts = [
            (new SubscriptionPaid('Базовый', 1490, Carbon::parse('2026-10-24')))->toDatabase($teacher)['body'],
            (new SubscriptionExpiringSoon('Базовый', Carbon::parse('2026-09-27 12:00')))->toDatabase($teacher)['body'],
            (new SubscriptionAutoRenewNotice('Базовый', 1490, Carbon::parse('2026-09-27')))->toDatabase($teacher)['body'],
        ];

        $this->assertSame('Оплата 1 490 ₽ за тариф «Базовый» прошла. Тариф действует до 24 октября', $texts[0]);
        $this->assertStringContainsString('через 3 дня, 27 сентября', $texts[1]);
        foreach ($texts as $text) {
            $this->assertDoesNotMatchRegularExpression('/\d{2}\.\d{2}\.\d{4}|\(а\)| дн\./u', $text);
        }

        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Алина Смирнова']);
        $homework = Homework::create(['teacher_id' => $teacher->id, 'title' => 'Эссе', 'type' => 'homework', 'is_visible' => true]);
        $this->assertSame('Алина Смирнова · «Эссе»', (new HomeworkSubmitted($homework, $student))->toDatabase($teacher)['body']);
    }

    public function test_homework_deadline_reminder(): void
    {
        Notification::fake();
        $teacher = $this->user(User::ROLE_TUTOR);
        [$pending, $done, $revision] = [$this->user(User::ROLE_STUDENT), $this->user(User::ROLE_STUDENT), $this->user(User::ROLE_STUDENT)];

        $homework = Homework::create(['teacher_id' => $teacher->id, 'title' => 'Эссе', 'type' => 'homework', 'is_visible' => true, 'deadline' => '2026-09-25 10:00:00']);
        $homework->forceFill(['created_at' => '2026-09-20 10:00:00'])->saveQuietly();
        $homework->students()->attach([$pending->id, $done->id, $revision->id]);
        HomeworkSubmission::create(['homework_id' => $homework->id, 'student_id' => $done->id, 'status' => HomeworkSubmission::STATUS_SUBMITTED, 'submitted_at' => now()]);
        HomeworkSubmission::create(['homework_id' => $homework->id, 'student_id' => $revision->id, 'status' => HomeworkSubmission::STATUS_REVISION_REQUESTED, 'submitted_at' => now()->subDay()]);

        // Выдано только что — срок уже в уведомлении «Новое задание»
        $fresh = Homework::create(['teacher_id' => $teacher->id, 'title' => 'Тест', 'type' => 'homework', 'is_visible' => true, 'deadline' => '2026-09-25 09:00:00']);
        $fresh->students()->attach($pending->id);

        $this->artisan('homework:remind-deadlines')->assertSuccessful();
        $this->artisan('homework:remind-deadlines')->assertSuccessful();

        Notification::assertSentToTimes($pending, HomeworkDeadlineSoon::class, 1);
        Notification::assertSentTo($revision, HomeworkDeadlineSoon::class, fn ($n) => $n->revision
            && str_contains($n->toDatabase($revision)['body'], 'сдать исправленную работу до пт, 25 сентября в 10:00'));
        Notification::assertNothingSentTo($done);
    }

    public function test_lessons_running_out_once_per_threshold(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\TariffSeeder::class);
        $teacher = $this->user(User::ROLE_TUTOR);
        $tariff = \App\Models\Tariff::where('slug', 'basic')->first();
        $tariff->update(['lessons_per_month' => 3]);
        \App\Services\SubscriptionService::activate($teacher, $tariff);
        $student = $this->user(User::ROLE_STUDENT);
        $room = $this->room($teacher, [$student]);

        $complete = function () use ($teacher, $room) {
            $session = \App\Models\MeetingSession::create([
                'room_id' => $room->id, 'user_id' => $teacher->id, 'meeting_id' => $room->meeting_id, 'status' => 'running',
                'started_at' => now(), 'participant_count' => 2,
            ]);
            $session->update(['status' => 'completed', 'ended_at' => now()->addMinutes(45)]);
        };

        $complete(); // осталось 2 — «заканчиваются»
        $complete(); // осталось 1 — порог «заканчиваются» уже был
        $complete(); // 0 — «закончились»

        Notification::assertSentToTimes($teacher, LessonsRunningOut::class, 2);
        Notification::assertSentTo($teacher, LessonsRunningOut::class, fn ($n) => $n->left === 0
            && $n->toDatabase($teacher)['title'] === 'Занятия по тарифу закончились');
    }
}
