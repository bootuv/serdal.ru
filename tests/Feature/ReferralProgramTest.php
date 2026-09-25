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
            ->assertSee('Вас пригласил(а) Анна Петрова')
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

    public function test_tutor_referrals_page_renders(): void
    {
        $referrer = $this->makeTutor();
        SubscriptionService::activate($referrer, Tariff::where('slug', 'start')->first());
        $this->makeTutor(['referred_by_id' => $referrer->id, 'name' => 'Коллега Первый']);

        $this->actingAs($referrer)
            ->get(\App\Filament\App\Pages\Referrals::getUrl(panel: 'app'))
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

        $this->actingAs($admin)
            ->get('/admin/settings?tab=-партнёрская-программа-tab')
            ->assertOk()
            ->assertSee('Бонус пригласившему, занятий');

        $this->actingAs($admin)->get('/admin/referral-rewards')->assertOk();
    }

    public function test_dashboard_banner_waits_for_delay_and_hides_on_dismiss(): void
    {
        $fresh = $this->makeTutor();
        $this->assertFalse(ReferralService::shouldShowBanner($fresh));

        $tutor = $this->makeTutor(['created_at' => now()->subDays(10)]);
        $this->assertTrue(ReferralService::shouldShowBanner($tutor));

        $this->actingAs($tutor);
        Livewire::test(\App\Filament\App\Widgets\ReferralBannerWidget::class)
            ->assertSee('Приглашайте коллег')
            ->call('dismiss')
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
            ->assertOk()
            ->assertSeeLivewire(\App\Filament\App\Widgets\ReferralBannerWidget::class)
            ->assertSee('Пригласить коллегу');
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

        $this->actingAs($tutor);
        Livewire::test(\App\Filament\App\Widgets\TariffLimitsWidget::class)
            ->assertSee('пригласите коллегу');
    }
}
