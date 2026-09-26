<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Referrals;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Services\ReferralService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Приглашения (/cabinet/admin/referrals): журнал начислений, сводка, настройки программы. */
class ReferralsTest extends TestCase
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
        return User::factory()->create(array_merge(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true], $attrs));
    }

    private function reward(User $from, User $to, string $status, array $attrs = []): ReferralReward
    {
        $payment = SubscriptionPayment::create(['user_id' => $to->id, 'tariff_id' => Tariff::where('slug', 'basic')->value('id'), 'amount' => 1490, 'period_days' => 30, 'status' => 'paid', 'gateway' => 'yookassa']);

        return ReferralReward::create(array_merge(['referrer_id' => $from->id, 'referred_id' => $to->id, 'payment_id' => $payment->id, 'referrer_lessons' => 10, 'referred_lessons' => 5, 'status' => $status], $attrs));
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/referrals')->assertRedirect(route('login'));
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->actingAs($tutor)->get('/cabinet/admin/referrals')->assertRedirect(EnsureCabinetRole::homeFor($tutor));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/cabinet/admin/referrals')->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get('/cabinet/admin/referrals')->assertOk()->assertSee('Партнёрская программа')->assertSee('Пока никто не приглашал коллег.');
    }

    public function test_journal_filters_search_summary_and_top(): void
    {
        $ivan = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $gleb = $this->user(User::ROLE_TUTOR, ['name' => 'Глеб Сорокин', 'referred_by_id' => $ivan->id]);
        $olga = $this->user(User::ROLE_TUTOR, ['name' => 'Олеся Смирнова', 'referred_by_id' => $ivan->id]);
        $maria = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $sergey = $this->user(User::ROLE_TUTOR, ['name' => 'Сергей Павлов', 'referred_by_id' => $maria->id]);

        $this->reward($ivan, $gleb, ReferralReward::STATUS_LIMIT, ['referrer_lessons' => 0, 'note' => 'Превышен лимит']);
        $this->reward($ivan, $olga, ReferralReward::STATUS_REJECTED, ['referrer_lessons' => 0, 'referred_lessons' => 0, 'note' => 'Совпадает телефон пригласившего и приглашённого']);
        $this->reward($maria, $sergey, ReferralReward::STATUS_CREDITED);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Referrals::class)
            ->assertSee('Включена · 10 занятий пригласившему, 5 приглашённому · без лимита в месяц')
            ->assertSee('Лимит в месяц · 1')
            ->assertSee('Отклонено · 1')
            ->assertSee('похоже на приглашение себя — совпал телефон')
            ->assertSee('Пришли по приглашению')
            ->assertSee('10 — пригласившим, 10 — приглашённым')
            ->assertSee('2 коллеги')
            ->set('filter', ReferralReward::STATUS_REJECTED)
            ->assertSee('Олеся Смирнова')
            ->assertDontSee('Сергей Павлов')
            ->set('filter', 'all')
            ->set('q', 'Мария')
            ->assertSee('Сергей Павлов')
            ->assertDontSee('Глеб Сорокин');
    }

    public function test_settings_saved_to_same_keys(): void
    {
        $c = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Referrals::class)
            ->call('openSettings')
            ->assertSet('bonusReferrer', '10')
            ->assertSee('Начисленные бонусы изменения не затронут')
            ->set('bonusReferrer', '12')
            ->set('bonusReferred', '3')
            ->set('monthlyLimit', '4')
            ->set('cookieDays', '')
            ->call('saveSettings')
            ->assertHasErrors(['cookieDays'])
            ->set('cookieDays', '45')
            ->call('toggleBanner')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Настройки программы сохранены');

        $this->assertSame(12, ReferralService::referrerBonus());
        $this->assertSame(3, ReferralService::referredBonus());
        $this->assertSame(4, ReferralService::monthlyLimit());
        $this->assertSame(45, ReferralService::cookieDays());
        $this->assertFalse(ReferralService::bannerEnabled());
        $this->assertSame('0', Setting::where('key', 'referral_banner_enabled')->value('value'));

        $c->call('openSettings')->set('on', false)->call('saveSettings');
        $this->assertFalse(ReferralService::enabled());
        $c->assertSee('Выключена — новые бонусы не начисляются');
    }
}
