<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Today;
use App\Models\MeetingSession;
use App\Models\Review;
use App\Models\Room;
use App\Models\SubscriptionPayment;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\AdminTodayService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Сегодня (/cabinet/admin): очередь исключений, идущие занятия, итоги и график. */
class TodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
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

    private function room(User $teacher, string $name, array $students = [], array $attrs = []): Room
    {
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => $name,
            'meeting_id' => 'm-' . uniqid(),
            'moderator_pw' => 'mod',
            'attendee_pw' => 'att',
        ]);
        $room->participants()->attach(collect($students)->pluck('id'));

        return $room;
    }

    private function meeting(Room $room, $startedAt, array $attrs = []): MeetingSession
    {
        return MeetingSession::create($attrs + [
            'user_id' => $room->user_id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->addHour(),
            'status' => 'completed',
        ]);
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin')->assertRedirect(route('login'));

        $teacher = $this->user(User::ROLE_TUTOR);
        $this->actingAs($teacher)->get('/cabinet/admin')->assertRedirect(EnsureCabinetRole::homeFor($teacher));

        $student = $this->user(User::ROLE_STUDENT);
        $this->actingAs($student)->get('/cabinet/admin')->assertRedirect(route('cabinet.student.home'));

        $this->actingAs($this->user(User::ROLE_ADMIN))->get('/cabinet/admin')
            ->assertOk()
            ->assertSee('Сегодня')
            ->assertSee('Всё разобрано')
            ->assertSee('Новых заявок, жалоб, обращений и зависших платежей нет')
            ->assertSee('Занятия по дням');
    }

    public function test_queue_lists_exceptions_with_links(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Иван Орлов']);

        // Обращение в поддержку, ждёт 3 часа; ответ поддержки не считается
        $chat = SupportChat::create(['user_id' => $student->id]);
        $old = SupportMessage::create(['support_chat_id' => $chat->id, 'user_id' => $student->id, 'content' => 'Не могу войти']);
        $old->forceFill(['created_at' => now()->subHours(3)])->save();
        SupportMessage::create(['support_chat_id' => $chat->id, 'user_id' => $admin->id, 'content' => 'Сейчас посмотрим']);

        // Заявка, пришла 2 дня назад
        $app = TeacherApplication::create(['first_name' => 'Дмитрий', 'last_name' => 'Зайцев', 'email' => 'dz@mail.ru', 'status' => 'pending']);
        $app->forceFill(['created_at' => now()->subDays(2)])->save();

        // Платёж ждёт оплаты больше суток; свежий и привязку карты не показываем
        $pro = Tariff::where('slug', 'pro')->first();
        $stuck = SubscriptionPayment::create(['user_id' => $teacher->id, 'tariff_id' => $pro->id, 'amount' => 2990, 'period_days' => 30, 'status' => 'pending']);
        $stuck->forceFill(['created_at' => now()->subDays(2)])->save();
        SubscriptionPayment::create(['user_id' => $teacher->id, 'tariff_id' => $pro->id, 'amount' => 2990, 'period_days' => 30, 'status' => 'pending']);
        $binding = SubscriptionPayment::create(['user_id' => $teacher->id, 'tariff_id' => $pro->id, 'amount' => 1, 'period_days' => 0, 'status' => 'pending', 'meta' => ['card_binding' => true]]);
        $binding->forceFill(['created_at' => now()->subDays(2)])->save();

        // Жалоба на отзыв
        Review::create(['teacher_id' => $teacher->id, 'user_id' => $student->id, 'rating' => 2, 'text' => 'Плохо', 'is_reported' => true, 'report_reason' => 'rude', 'reported_at' => now()]);

        // Запрос на удаление занятия
        $room = $this->room($teacher, 'Английский язык', [$student]);
        $this->meeting($room, now()->subDays(2), ['deletion_requested_at' => now(), 'deletion_reason' => 'Тест']);

        $queue = collect(app(AdminTodayService::class)->queue())->keyBy('title');
        $this->assertSame(['Обращения в поддержку', 'Заявки учителей', 'Платежи ждут оплаты больше суток', 'Жалобы на отзывы', 'Запросы на удаление занятий'], $queue->keys()->all());
        $this->assertSame(1, $queue['Обращения в поддержку']['n']);
        $this->assertSame('самое давнее ждёт 3 часа', $queue['Обращения в поддержку']['em']);
        $this->assertSame('ждёт 2 дня', $queue['Заявки учителей']['em']);
        $this->assertSame(1, $queue['Платежи ждут оплаты больше суток']['n']);
        $this->assertStringContainsString('«Профи» 2 990 ₽', $queue['Платежи ждут оплаты больше суток']['who']);
        $this->assertStringContainsString('оскорбления или грубость', $queue['Жалобы на отзывы']['who']);
        $this->assertSame(route('cabinet.admin.support', ['filter' => 'unread']), $queue['Обращения в поддержку']['href']);

        $this->actingAs($admin)->get('/cabinet/admin')
            ->assertOk()
            ->assertSee('Нужно разобрать')
            ->assertSee('Дмитрий Зайцев')
            ->assertSee('Иван Орлов')
            ->assertDontSee('Всё разобрано');
    }

    public function test_live_lessons_with_join_link(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Софья Новикова']);
        $room = $this->room($teacher, 'История', [$student], ['is_running' => true]);
        $this->meeting($room, now()->subMinutes(130), ['status' => 'running', 'ended_at' => null]);

        $this->actingAs($admin)->get('/cabinet/admin')
            ->assertOk()
            ->assertSee('Идут сейчас: 1 занятие')
            ->assertSee('История · Иван Орлов')
            ->assertSee('Софья Новикова')
            ->assertSee('идёт 2 часа 10 минут')
            ->assertSee('план — 60')
            ->assertSee('Подключиться')
            ->assertSee(route('rooms.connect', $room->id), false);
    }

    public function test_period_stats_and_teacher_filter(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $maria = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $ivan = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $s1 = $this->user(User::ROLE_STUDENT);
        $s2 = $this->user(User::ROLE_STUDENT);

        $mariaRoom = $this->room($maria, 'Английский', [$s1, $s2]);
        $ivanRoom = $this->room($ivan, 'История', [$s1]);
        $this->meeting($mariaRoom, now()->subDays(1));
        $this->meeting($mariaRoom, now()->subDays(3));
        $this->meeting($ivanRoom, now()->subDays(2));
        $this->meeting($ivanRoom, now()->subDays(20)); // только в 30 днях

        $pro = Tariff::where('slug', 'pro')->first();
        SubscriptionPayment::create(['user_id' => $maria->id, 'tariff_id' => $pro->id, 'amount' => 2990, 'period_days' => 30, 'status' => 'paid', 'paid_at' => now()->subDay()]);

        $page = Livewire::actingAs($admin)->test(Today::class)
            ->assertSee('За 7 дней')
            ->assertSee('проведено занятия')
            ->assertSee('2 990 ₽')
            ->assertSee('оплат за тарифы')
            ->assertSee('новых учителя');
        $this->assertSame(3, collect($page->viewData('summary')['chart']['bars'])->sum('v'));
        $this->assertCount(7, $page->viewData('summary')['chart']['bars']);

        $page->set('period', 30);
        $this->assertSame(4, collect($page->viewData('summary')['chart']['bars'])->sum('v'));
        $this->assertCount(30, $page->viewData('summary')['chart']['bars']);
        $page->assertSee('За 30 дней');

        $page->set('period', 7)->set('teacher', (string) $maria->id)
            ->assertSee('За 7 дней: Мария Соколова')
            ->assertSee('ученика занимались')
            ->assertSee('оплата за тариф');
        $this->assertSame(2, collect($page->viewData('summary')['chart']['bars'])->sum('v'));

        $page->set('teacher', (string) $ivan->id)->assertSee('Нет оплат');
    }

    public function test_chart_marks_today_and_average(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $teacher = $this->user(User::ROLE_TUTOR);
        $room = $this->room($teacher, 'Физика');
        for ($i = 0; $i < 14; $i++) {
            $this->meeting($room, now()->startOfDay()->addHours(10)->addMinutes($i));
        }

        $chart = app(AdminTodayService::class)->period(7)['chart'];
        $last = end($chart['bars']);
        $this->assertTrue($last['now']);
        $this->assertSame(14, $last['v']);
        $this->assertSame('в среднем 2', $chart['avgText']);
        $this->assertSame('20', $chart['ticks'][0]['label']);

        $this->actingAs($admin)->get('/cabinet/admin')->assertSee('fill-chart-1', false)->assertSee('в среднем 2');
    }
}
