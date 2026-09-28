<?php

namespace Tests\Feature;

use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
        Notification::fake();
    }

    protected function makeTutor(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
            'phone' => '+7 900 ' . random_int(1000000, 9999999),
        ]);
    }

    protected function pay(User $user, string $slug = 'basic'): SubscriptionPayment
    {
        $tariff = Tariff::where('slug', $slug)->first();

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'amount' => $tariff->price,
            'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
        ]);

        SubscriptionService::applyPaidPayment($payment);

        return $payment->fresh();
    }

    public function test_first_payment_credits_both_teachers(): void
    {
        $referrer = $this->makeTutor();
        $referred = $this->makeTutor(['referred_by_id' => $referrer->id]);

        $this->pay($referred);

        $this->assertEquals(10, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(5, $referred->fresh()->extra_lessons_balance);
        $this->assertEquals(ReferralReward::STATUS_CREDITED, ReferralReward::first()->status);

        // Продление бонусов не даёт
        $this->pay($referred);
        $this->assertEquals(10, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(1, ReferralReward::count());
    }

    public function test_bonuses_follow_admin_settings_and_tariff_override(): void
    {
        Setting::updateOrCreate(['key' => 'referral_bonus_referrer'], ['value' => '7']);
        Setting::updateOrCreate(['key' => 'referral_bonus_referred'], ['value' => '0']);
        Tariff::where('slug', 'pro')->update(['referral_bonus' => 25]);

        $referrer = $this->makeTutor();
        $basic = $this->makeTutor(['referred_by_id' => $referrer->id]);
        $pro = $this->makeTutor(['referred_by_id' => $referrer->id]);

        $this->pay($basic, 'basic');
        $this->pay($pro, 'pro');

        $this->assertEquals(32, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(0, $basic->fresh()->extra_lessons_balance);
    }

    public function test_disabled_program_does_not_credit(): void
    {
        Setting::updateOrCreate(['key' => 'referral_enabled'], ['value' => '0']);

        $referrer = $this->makeTutor();
        $referred = $this->makeTutor(['referred_by_id' => $referrer->id]);

        $this->pay($referred);

        $this->assertEquals(0, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(0, ReferralReward::count());
    }

    public function test_monthly_limit_credits_only_referred(): void
    {
        Setting::updateOrCreate(['key' => 'referral_monthly_limit'], ['value' => '1']);

        $referrer = $this->makeTutor();
        $first = $this->makeTutor(['referred_by_id' => $referrer->id]);
        $second = $this->makeTutor(['referred_by_id' => $referrer->id]);

        $this->pay($first);
        $this->pay($second);

        $this->assertEquals(10, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(5, $second->fresh()->extra_lessons_balance);
        $this->assertEquals(ReferralReward::STATUS_LIMIT, ReferralReward::where('referred_id', $second->id)->value('status'));
    }

    public function test_self_referral_by_same_phone_is_rejected(): void
    {
        $referrer = $this->makeTutor(['phone' => '+7 (900) 111-22-33']);
        $referred = $this->makeTutor(['referred_by_id' => $referrer->id, 'phone' => '89001112233']);

        $this->pay($referred);

        $this->assertEquals(0, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(0, $referred->fresh()->extra_lessons_balance);
        $this->assertEquals(ReferralReward::STATUS_REJECTED, ReferralReward::first()->status);
    }

    public function test_refund_revokes_bonuses_without_negative_balance(): void
    {
        $referrer = $this->makeTutor();
        $referred = $this->makeTutor(['referred_by_id' => $referrer->id]);

        $payment = $this->pay($referred);
        $referrer->update(['extra_lessons_balance' => 4]); // часть бонуса уже потрачена

        $payment->update(['status' => SubscriptionPayment::STATUS_REFUNDED]);
        SubscriptionService::applyRefund($payment);

        $this->assertEquals(0, $referrer->fresh()->extra_lessons_balance);
        $this->assertEquals(0, $referred->fresh()->extra_lessons_balance);
        $this->assertEquals(ReferralReward::STATUS_REVOKED, ReferralReward::first()->status);
    }

    public function test_invite_link_sets_cookie_and_application_stores_referrer(): void
    {
        $referrer = $this->makeTutor();
        $code = $referrer->referralCode();

        $this->get('/r/' . $code)
            ->assertRedirect(route('become-tutor'))
            ->assertCookie(ReferralService::COOKIE, $code);

        $this->assertEquals($referrer->id, ReferralService::referrerForApplication($code, 'new@example.com')?->id);
        // Себя пригласить нельзя
        $this->assertNull(ReferralService::referrerForApplication($code, $referrer->email));
    }

    public function test_become_tutor_page_shows_referrer_and_saves_it(): void
    {
        $referrer = $this->makeTutor(['name' => 'Анна Петрова']);
        $subject = \App\Models\Subject::create(['name' => 'Математика']);
        $direct = \App\Models\Direct::create(['name' => 'ЕГЭ']);

        Livewire::withQueryParams(['ref' => $referrer->referralCode()])
            ->test(\App\Livewire\BecomeTutorPage::class)
            ->assertSee('Приглашение от Анна Петрова')
            ->set('data.last_name', 'Иванов')
            ->set('data.first_name', 'Иван')
            ->set('data.middle_name', 'Иванович')
            ->set('data.email', 'ivanov@example.com')
            ->set('data.phone', '+79005554433')
            ->set('data.subjects', [$subject->id])
            ->set('data.directs', [$direct->id])
            ->set('data.grade', ['5'])
            ->set('data.about', 'Опыт 10 лет')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertEquals($referrer->id, TeacherApplication::where('email', 'ivanov@example.com')->value('referred_by_id'));
    }

    public function test_logged_in_user_opening_invite_link_gets_no_cookie(): void
    {
        $referrer = $this->makeTutor();
        $code = $referrer->referralCode();

        $this->actingAs($referrer)
            ->get('/r/' . $code)
            ->assertRedirect(route('become-tutor'))
            ->assertCookieMissing(ReferralService::COOKIE);
    }

    public function test_application_without_invite_has_no_referrer(): void
    {
        $subject = \App\Models\Subject::create(['name' => 'Математика']);
        $direct = \App\Models\Direct::create(['name' => 'ЕГЭ']);

        $this->fillApplication(Livewire::test(\App\Livewire\BecomeTutorPage::class), $subject, $direct, 'plain@example.com')
            ->assertDontSee('Приглашение от')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertNull(TeacherApplication::where('email', 'plain@example.com')->value('referred_by_id'));
    }

    public function test_applicant_can_decline_referral(): void
    {
        $referrer = $this->makeTutor(['name' => 'Анна Петрова']);
        $subject = \App\Models\Subject::create(['name' => 'Математика']);
        $direct = \App\Models\Direct::create(['name' => 'ЕГЭ']);

        $component = Livewire::withQueryParams(['ref' => $referrer->referralCode()])
            ->test(\App\Livewire\BecomeTutorPage::class)
            ->assertSee('Приглашение от Анна Петрова')
            ->call('declineReferral')
            ->assertDontSee('Приглашение от');

        $this->fillApplication($component, $subject, $direct, 'solo@example.com')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertNull(TeacherApplication::where('email', 'solo@example.com')->value('referred_by_id'));
    }

    public function test_second_application_from_same_browser_is_not_referred(): void
    {
        $referrer = $this->makeTutor();
        $subject = \App\Models\Subject::create(['name' => 'Математика']);
        $direct = \App\Models\Direct::create(['name' => 'ЕГЭ']);

        $component = Livewire::withQueryParams(['ref' => $referrer->referralCode()])
            ->test(\App\Livewire\BecomeTutorPage::class);

        $this->fillApplication($component, $subject, $direct, 'first@example.com')->call('create')->assertHasNoErrors();
        $this->fillApplication($component->set('isSubmitted', false), $subject, $direct, 'second@example.com')->call('create')->assertHasNoErrors();

        $this->assertEquals($referrer->id, TeacherApplication::where('email', 'first@example.com')->value('referred_by_id'));
        $this->assertNull(TeacherApplication::where('email', 'second@example.com')->value('referred_by_id'));
    }

    public function test_logged_in_user_sees_no_referrer_on_application_page(): void
    {
        $referrer = $this->makeTutor(['name' => 'Анна Петрова']);

        Livewire::actingAs($referrer)
            ->withQueryParams(['ref' => $referrer->referralCode()])
            ->test(\App\Livewire\BecomeTutorPage::class)
            ->assertDontSee('Приглашение от');
    }

    public function test_login_forgets_invite_cookie(): void
    {
        $user = $this->makeTutor();

        request()->cookies->set(ReferralService::COOKIE, 'abc');
        event(new \Illuminate\Auth\Events\Login('web', $user, false));

        $this->assertTrue(collect(\Illuminate\Support\Facades\Cookie::getQueuedCookies())
            ->contains(fn ($cookie) => $cookie->getName() === ReferralService::COOKIE && $cookie->getExpiresTime() < time()));
    }

    public function test_no_bonus_if_teacher_paid_before_program_was_enabled(): void
    {
        $referrer = $this->makeTutor();
        $referred = $this->makeTutor(['referred_by_id' => $referrer->id]);

        Setting::updateOrCreate(['key' => 'referral_enabled'], ['value' => '0']);
        $this->pay($referred);

        Setting::updateOrCreate(['key' => 'referral_enabled'], ['value' => '1']);
        $this->pay($referred);

        $this->assertEquals(0, ReferralReward::count());
        $this->assertEquals(0, (int) $referrer->fresh()->extra_lessons_balance);
    }

    protected function fillApplication($component, $subject, $direct, string $email)
    {
        return $component
            ->set('data.last_name', 'Иванов')
            ->set('data.first_name', 'Иван')
            ->set('data.middle_name', 'Иванович')
            ->set('data.email', $email)
            ->set('data.phone', '+79005554433')
            ->set('data.subjects', [$subject->id])
            ->set('data.directs', [$direct->id])
            ->set('data.grade', ['5'])
            ->set('data.about', 'Опыт 10 лет');
    }

    public function test_tutor_referrals_page_renders(): void
    {
        $referrer = $this->makeTutor();
        SubscriptionService::activate($referrer, Tariff::where('slug', 'start')->first());
        $this->makeTutor(['referred_by_id' => $referrer->id, 'name' => 'Коллега Первый']);

        // Старый адрес ведёт в новый кабинет
        $this->actingAs($referrer)
            ->get('/tutor/referrals')
            ->assertRedirect(route('cabinet.teacher.referrals'));

        $this->actingAs($referrer)
            ->get(route('cabinet.teacher.referrals'))
            ->assertOk()
            ->assertSee('Пригласите коллегу')
            ->assertSee('/r/' . $referrer->fresh()->referral_code)
            ->assertSee('Коллега Первый');
    }

    public function test_admin_settings_page_shows_referral_settings(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'username' => 'admin' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);

        $this->actingAs($admin)->get(route('cabinet.admin.referrals'))->assertOk();
        Livewire::test(\App\Livewire\Cabinet\Admin\Referrals::class)
            ->call('openSettings')
            ->assertSee('Бонус пригласившему');

        $this->actingAs($admin)->get('/admin/referral-rewards')->assertRedirect(route('cabinet.admin.referrals'));
    }

    public function test_dashboard_banner_waits_for_delay_and_hides_on_dismiss(): void
    {
        $fresh = $this->makeTutor();
        $this->assertFalse(ReferralService::shouldShowBanner($fresh));

        $tutor = $this->makeTutor(['created_at' => now()->subDays(10)]);
        $this->assertTrue(ReferralService::shouldShowBanner($tutor));

        // Плашка в сайдбаре кабинета учителя: крестик скрывает её
        $this->actingAs($tutor);
        Livewire::test(\App\Livewire\Cabinet\ReferralPromo::class)
            ->assertSee('Приглашайте коллег')
            ->call('hide')
            ->assertSet('visible', false)
            ->assertDontSee('Приглашайте коллег');

        $this->assertFalse(ReferralService::shouldShowBanner($tutor->fresh()));

        // Через месяц баннер возвращается
        $this->travel(31)->days();
        $this->assertTrue(ReferralService::shouldShowBanner($tutor->fresh()));
    }

    public function test_banner_can_be_disabled_in_settings(): void
    {
        Setting::updateOrCreate(['key' => 'referral_banner_enabled'], ['value' => '0']);

        $tutor = $this->makeTutor(['created_at' => now()->subDays(10)]);

        $this->assertFalse(ReferralService::shouldShowBanner($tutor));
    }

    public function test_dashboard_shows_banner_and_sidebar_item(): void
    {
        $tutor = $this->makeTutor(['created_at' => now()->subDays(10)]);
        SubscriptionService::activate($tutor, Tariff::where('slug', 'start')->first());

        $this->actingAs($tutor)
            ->get('/tutor')
            ->assertRedirect(route('cabinet.teacher.today'));

        // Плашка в сайдбаре ведёт на партнёрку; пока она видна, отдельной ссылки нет
        $this->actingAs($tutor)
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSeeLivewire(\App\Livewire\Cabinet\ReferralPromo::class)
            ->assertSee('Приглашайте коллег')
            ->assertSee('Пригласить коллегу')
            ->assertSee(route('cabinet.teacher.referrals'))
            ->assertDontSee('Пригласить коллег</a>', false);

        // Плашку скрыли — остаётся пункт «Пригласить коллег» в сайдбаре
        ReferralService::hideBanner($tutor);
        $this->actingAs($tutor->fresh())
            ->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertDontSee('Приглашайте коллег')
            ->assertSee('Пригласить коллег</a>', false)
            ->assertSee(route('cabinet.teacher.referrals'));
    }

    public function test_limit_hint_shown_when_lessons_run_out(): void
    {
        $tutor = $this->makeTutor();
        $start = Tariff::where('slug', 'start')->first();
        SubscriptionService::activate($tutor, $start)->update(['starts_at' => now()->subDay()]);
        $room = \App\Models\Room::create([
            'user_id' => $tutor->id,
            'name' => 'Занятие',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);

        for ($i = 0; $i < $start->lessons_per_month; $i++) {
            \App\Models\MeetingSession::create([
                'user_id' => $tutor->id,
                'room_id' => $room->id,
                'meeting_id' => 'm' . $i,
                'status' => 'completed',
                'participant_count' => 2,
                'started_at' => now()->subHours(2),
                'ended_at' => now()->subHour(),
            ]);
        }
        SubscriptionService::flushCanStartCache();

        // Экран «Тариф и платежи»: лимит исчерпан — подсказка про приглашение коллеги
        $this->actingAs($tutor);
        Livewire::test(\App\Livewire\Cabinet\Teacher\Subscription::class)
            ->assertSee('пригласите коллегу')
            ->assertSee(route('cabinet.teacher.referrals'));
    }
}
