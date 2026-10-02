<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\User as UserCard;
use App\Models\LessonType;
use App\Models\PaymentRecord;
use App\Models\Subject;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Notifications\SubscriptionAssigned;
use App\Services\SubscriptionService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Cabinet\TeacherLessonFixtures;
use Tests\TestCase;

/** Админка → карточка пользователя (/cabinet/admin/users/{user}): учитель, ученик, администратор. */
class UserTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
        $this->fixNow();
        $this->seed(\Database\Seeders\TariffSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(array $attrs = []): User
    {
        return $this->user(User::ROLE_ADMIN, $attrs + ['first_name' => 'Анна', 'last_name' => 'Куликова']);
    }

    private function mariya(): User
    {
        return $this->user(User::ROLE_TUTOR, ['first_name' => 'Мария', 'last_name' => 'Соколова', 'email' => 'm.sokolova@mail.ru', 'phone' => '+7 916 245-18-73']);
    }

    public function test_access(): void
    {
        $teacher = $this->mariya();
        $url = route('cabinet.admin.user', ['user' => $teacher->id]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($teacher)->get($url)->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get($url)->assertRedirect(route('cabinet.student.home'));

        $admin = $this->admin();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Соколова Мария')->assertSee('m.sokolova@mail.ru')->assertSee('на Serdal с');
        $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => 999999]))->assertNotFound();
    }

    public function test_teacher_overview_tariff_payments_and_no_lesson_payments(): void
    {
        $teacher = $this->mariya();
        $pro = Tariff::where('slug', 'pro')->first();
        SubscriptionService::activate($teacher, $pro, days: 30);
        SubscriptionPayment::create(['user_id' => $teacher->id, 'tariff_id' => $pro->id, 'amount' => $pro->price, 'period_days' => 30, 'status' => SubscriptionPayment::STATUS_FAILED, 'gateway' => 'yookassa']);
        SubscriptionPayment::create(['user_id' => $teacher->id, 'tariff_id' => $pro->id, 'amount' => 1000, 'extra_lessons' => 10, 'status' => SubscriptionPayment::STATUS_PAID, 'paid_at' => now(), 'gateway' => 'yookassa']);
        $teacher->update(['extra_lessons_balance' => 5]);

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])
            ->assertSee('Тариф «' . $pro->name . '»')
            ->assertSee('Занятия в этом периоде')
            ->assertSee('Назначить тариф')
            ->assertSee('Не прошёл')
            ->assertSee('Дополнительные занятия')
            ->assertSee('5 занятий')
            ->assertSee('Активность')
            ->assertDontSee('Не оплачено')
            ->assertDontSee('Просрочено');
    }

    public function test_admin_grants_lessons_to_teacher_with_expired_tariff(): void
    {
        Notification::fake();
        $teacher = $this->mariya();
        $admin = $this->admin();
        $basic = Tariff::where('price', '>', 0)->orderBy('price')->first();
        $sub = SubscriptionService::activate($teacher, $basic, days: 30);
        $sub->update(['starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);
        SubscriptionService::flushCanStartCache();

        $this->assertNull($teacher->fresh()->activeSubscription());
        $this->assertNotNull(SubscriptionService::canStartLesson($teacher->fresh()));

        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $teacher->id])
            ->assertSee('Тарифа нет')
            ->call('openLessons')
            ->assertSee('Тарифа сейчас нет')
            ->set('grantCount', 0)
            ->call('grantLessons')
            ->assertHasErrors('grantCount')
            ->set('grantCount', 1)
            ->set('grantNote', 'Тариф продлит вечером')
            ->call('grantLessons')
            ->assertHasNoErrors()
            ->assertSet('modal', null)
            ->assertSee('можно провести ещё 1 занятие с баланса')
            ->assertSee('Тариф продлит вечером');

        $teacher->refresh();
        $this->assertSame(1, (int) $teacher->extra_lessons_balance);
        $this->assertDatabaseHas('lesson_grants', ['user_id' => $teacher->id, 'admin_id' => $admin->id, 'lessons' => 1]);
        Notification::assertSentTo($teacher, \App\Notifications\LessonsGranted::class);

        // Без тарифа занятие можно начать, условия — последнего тарифа
        SubscriptionService::flushCanStartCache();
        $this->assertNull(SubscriptionService::canStartLesson($teacher));
        $this->assertSame($basic->max_participants, SubscriptionService::meetingLimits($teacher)['max_participants']);

        // Проведённое занятие списывается с баланса, после этого снова нельзя
        $room = \App\Models\Room::create(['user_id' => $teacher->id, 'name' => 'Алгебра', 'meeting_id' => 'grant-' . uniqid(), 'moderator_pw' => 'mp', 'attendee_pw' => 'ap']);
        $session = \App\Models\MeetingSession::create(['user_id' => $teacher->id, 'room_id' => $room->id, 'meeting_id' => $room->meeting_id, 'started_at' => now()->subHour(), 'status' => 'running', 'participant_count' => 0]);
        $session->update(['status' => 'completed', 'ended_at' => now(), 'participant_count' => 2]);

        $this->assertTrue($session->fresh()->extra_lesson);
        $this->assertSame(0, (int) $teacher->fresh()->extra_lessons_balance);
        SubscriptionService::flushCanStartCache();
        $this->assertNotNull(SubscriptionService::canStartLesson($teacher->fresh()));

        // Учитель продлил тариф — занятие за счёт баланса лимит нового периода не расходует
        SubscriptionService::activate($teacher->fresh(), $basic, days: 30);
        $this->assertSame(0, SubscriptionService::lessonsUsedThisPeriod($teacher->fresh()));
    }

    public function test_assign_tariff_free_forever_notifies_teacher(): void
    {
        Notification::fake();
        $teacher = $this->mariya();
        $admin = $this->admin();
        $master = Tariff::where('slug', 'master')->first();

        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $teacher->id])
            ->call('openTariff')
            ->assertSee('сейчас без тарифа')
            ->set('tariffId', $master->id)
            ->set('term', 'forever')
            ->set('free', true)
            ->set('note', 'за помощь с тестированием')
            ->call('assignTariff')
            ->assertHasNoErrors()
            ->assertSet('modal', null)
            ->assertDispatched('toast');

        $sub = $teacher->fresh()->activeSubscription();
        $this->assertSame($master->id, $sub->tariff_id);
        $this->assertNull($sub->ends_at);
        $this->assertTrue($sub->isComplimentary());
        $this->assertStringContainsString('Куликова Анна', $sub->comment);
        $this->assertStringContainsString('за помощь с тестированием', $sub->comment);
        Notification::assertSentTo($teacher, SubscriptionAssigned::class, fn ($n) => $n->tariffName === $master->name && $n->complimentary);
    }

    public function test_assign_tariff_custom_term(): void
    {
        Notification::fake();
        $teacher = $this->mariya();
        $basic = Tariff::where('slug', 'basic')->first();

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])
            ->call('openTariff')
            ->set('tariffId', $basic->id)
            ->set('term', 'custom')
            ->set('customDays', '')
            ->call('assignTariff')
            ->assertHasErrors('customDays')
            ->set('customDays', 45)
            ->call('assignTariff')
            ->assertHasNoErrors();

        $sub = $teacher->fresh()->activeSubscription();
        $this->assertSame(45, (int) round(now()->diffInDays($sub->ends_at)));
        $this->assertFalse($sub->isComplimentary());
    }

    public function test_hide_block_unblock(): void
    {
        $teacher = $this->mariya();
        $admin = $this->admin();
        $page = Livewire::actingAs($admin)->test(UserCard::class, ['user' => $teacher->id]);

        $page->call('toggleHidden')->assertSee('Скрыт из каталога');
        $this->assertFalse((bool) $teacher->fresh()->is_active);
        $page->call('toggleHidden');
        $this->assertTrue((bool) $teacher->fresh()->is_active);

        $page->call('openBlock')->assertSee('Заблокировать учителя?')->call('block')->assertSee('Заблокирован');
        $this->assertTrue((bool) $teacher->fresh()->is_blocked);

        // Заблокированного выкидывает из кабинета
        $this->actingAs($teacher->fresh())->get(route('cabinet.teacher.today'))->assertRedirect(route('login'));

        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $teacher->id])->assertSee('Разблокировать')->call('unblock');
        $this->assertFalse((bool) $teacher->fresh()->is_blocked);
    }

    public function test_blocked_teacher_is_not_in_catalog(): void
    {
        $teacher = $this->mariya();
        $this->get(route('tutors.show', ['username' => $teacher->username]))->assertOk();
        $teacher->update(['is_blocked' => true]);
        $this->get(route('tutors.show', ['username' => $teacher->username]))->assertNotFound();
    }

    public function test_cannot_block_or_delete_self(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $admin->id])
            ->assertDontSee('Другие действия')
            ->call('openBlock')->assertForbidden();
        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $admin->id])
            ->call('delete')->assertForbidden();

        $this->assertNotNull($admin->fresh());
        $this->assertFalse((bool) $admin->fresh()->is_blocked);
    }

    public function test_delete_teacher_with_consequences(): void
    {
        $teacher = $this->mariya();
        $student = $this->studentOf($teacher);
        $this->room($teacher, [$student]);

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])
            ->call('openDelete')
            ->assertSee('Удалить учителя?')
            ->assertSee('1 ученик больше не увидит эти занятия')
            ->assertSee('Отменить нельзя.')
            ->call('delete')
            ->assertRedirect(route('cabinet.admin.users'));

        $this->assertNull(User::find($teacher->id));
        $this->assertNotNull(User::find($student->id));
    }

    public function test_teacher_profile_save_and_password_link(): void
    {
        Notification::fake();
        $teacher = $this->mariya();
        $this->user(User::ROLE_STUDENT, ['email' => 'busy@mail.ru']);
        $math = Subject::create(['name' => 'Математика']);

        $page = Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])->set('tab', 'profile')
            ->assertSee('Чему учит')
            ->assertSee('Вход и контакты');

        $page->set('email', 'busy@mail.ru')->call('saveProfile')->assertHasErrors('email');

        $page->set('email', 'new@mail.ru')->set('addSubject', (string) $math->id)->call('toggleGrade', '9')
            ->set('telegram', '@sokolova_english')->set('about', '<p>Преподаю <b>английский</b></p>')
            ->call('saveProfile')->assertHasNoErrors()->assertDispatched('toast', message: 'Профиль сохранён');

        $teacher->refresh();
        $this->assertSame('new@mail.ru', $teacher->email);
        $this->assertSame('sokolova_english', $teacher->telegram);
        $this->assertSame([$math->id], $teacher->subjects()->pluck('subjects.id')->all());
        $this->assertContains('9', array_map('strval', $teacher->grade));

        $page->call('sendReset')->assertDispatched('toast', message: 'Ссылка отправлена на new@mail.ru');
        Notification::assertSentTo($teacher, ResetPassword::class);
    }

    public function test_teacher_prices_edit(): void
    {
        $teacher = $this->mariya();
        $lt = LessonType::create(['user_id' => $teacher->id, 'type' => LessonType::TYPE_INDIVIDUAL, 'payment_type' => 'per_lesson', 'price' => 1500, 'duration' => 60, 'payment_due_days' => 3]);

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])->set('tab', 'prices')
            ->assertSee('Индивидуальные занятия')
            ->assertSee('1 500 ₽')
            ->call('editPrice', $lt->id)
            ->set('pricePayment', 'monthly')
            ->set('price', '6000')
            ->set('priceDuration', 90)
            ->set('priceCount', 2)
            ->call('savePrice')
            ->assertHasNoErrors()
            ->assertSee('6 000 ₽');

        $lt->refresh();
        $this->assertSame('monthly', $lt->payment_type);
        $this->assertSame(2, (int) $lt->count_per_week);
        $this->assertSame(3, (int) $lt->payment_due_days); // особые условия не трогаем
    }

    public function test_teacher_people_tab(): void
    {
        $teacher = $this->mariya();
        $student = $this->studentOf($teacher, 'Смирнова Алина');
        $room = $this->room($teacher, [$student]);
        $this->onceAt($room, '2026-09-24 16:00');
        $this->completedSession($room, '2026-09-20 16:00', 60, []);

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])->set('tab', 'people')
            ->assertSee('Смирнова Алина')
            ->assertSee('Сегодня в 16:00')
            ->assertSee('Проведённые')
            ->assertSee('1 раз');
    }

    public function test_student_overview_lessons_and_profile(): void
    {
        $teacher = $this->mariya();
        $student = $this->studentOf($teacher, 'Ким Павел');
        $student->update(['grade' => [11], 'last_name' => 'Ким', 'first_name' => 'Павел']);
        $room = $this->room($teacher, [$student], ['name' => 'Математика']);
        $this->onceAt($room, '2026-09-25 18:00');
        $this->completedSession($room, '2026-09-21 17:30', 60, []);

        $page = Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $student->id])
            ->assertSee('11 класс')
            ->assertSee('Учителя и занятия')
            ->assertSee('Соколова Мария')
            ->assertSee('Завтра в 18:00')
            ->assertSee('Контакты')
            ->assertDontSee('Цены');

        $page->set('view', 'past')->assertSee('Не пришёл');

        $page->set('tab', 'profile')->set('grade', '10')->set('phone', '+7 925 310-44-18')->call('saveProfile')->assertHasNoErrors();
        $this->assertSame([10], $student->fresh()->grade);
    }

    public function test_delete_student(): void
    {
        $teacher = $this->mariya();
        $student = $this->studentOf($teacher, 'Ким Павел');

        Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $student->id])
            ->call('openDelete')
            ->assertSee('Удалить ученика?')
            ->assertSee('Ученик пропадёт из списков: Соколова Мария')
            ->call('delete')
            ->assertRedirect(route('cabinet.admin.users', ['tab' => 'students']));

        $this->assertNull(User::find($student->id));
        $this->assertSame(0, $teacher->students()->count());
    }

    public function test_admin_card(): void
    {
        $me = $this->admin();
        $other = $this->user(User::ROLE_ADMIN, ['first_name' => 'Роман', 'last_name' => 'Ткачёв', 'last_login_at' => Carbon::parse('2026-09-23 19:40')]);

        Livewire::actingAs($me)->test(UserCard::class, ['user' => $other->id])
            ->assertSee('Ткачёв Роман')
            ->assertSee('последний вход вчера в 19:40')
            ->assertSee('Другие действия')
            ->assertSee('Пароль');
    }

    public function test_write_opens_support_chat_with_person(): void
    {
        $teacher = $this->mariya();

        $page = Livewire::actingAs($this->admin())->test(UserCard::class, ['user' => $teacher->id])->call('write');
        $chat = \App\Models\SupportChat::where('user_id', $teacher->id)->firstOrFail();
        $page->assertRedirect(route('cabinet.admin.support', ['chat' => $chat->id]));
    }

    public function test_all_tabs_render(): void
    {
        $admin = $this->admin();
        $teacher = $this->mariya();
        $student = $this->studentOf($teacher);
        foreach (['overview', 'profile', 'prices', 'people'] as $tab) {
            $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => $teacher->id, 'tab' => $tab]))->assertOk();
        }
        foreach (['overview', 'profile'] as $tab) {
            $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => $student->id, 'tab' => $tab]))->assertOk();
        }
        $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => $admin->id]))->assertOk()->assertSee('Профиль');
        Livewire::actingAs($admin)->test(UserCard::class, ['user' => $teacher->id])->call('openTariff')->assertSee('Бессрочно')->assertSee('Без оплаты');
    }

    public function test_login_records_last_login(): void
    {
        $user = $this->user(User::ROLE_STUDENT);
        auth()->login($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /** Выход из админки — в своей карточке; карточка профиля в меню ведёт туда же. */
    public function test_admin_logs_out_from_own_card(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => $admin->id]))
            ->assertOk()->assertSee('Выйти')
            ->assertSee('href="' . route('cabinet.admin.user', ['user' => $admin->id]) . '"', false);
        $this->actingAs($admin)->get(route('cabinet.admin.user', ['user' => $other->id]))
            ->assertOk()->assertDontSee('Выйти');

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }
}
