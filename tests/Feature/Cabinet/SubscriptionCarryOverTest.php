<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Subscription as SubscriptionScreen;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Services\AdminPaymentsService;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Смена платного тарифа на другой платный: неиспользованный остаток текущего
 * пересчитывается по стоимости и добавляется к сроку нового.
 */
class SubscriptionCarryOverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
        Notification::fake();
        $this->freezeSecond();
    }

    private function tutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    private function tariff(string $slug): Tariff
    {
        return Tariff::where('slug', $slug)->first();
    }

    private function pay(User $tutor, Tariff $tariff, ?float $amount = null, ?int $days = null): SubscriptionPayment
    {
        $payment = SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $tariff->id,
            'amount' => $amount ?? $tariff->price,
            'period_days' => $days ?? $tariff->period_days,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
        ]);
        SubscriptionService::applyPaidPayment($payment);

        return $payment->fresh();
    }

    public function test_upgrade_adds_unused_value_as_days_of_new_tariff(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');   // 1 490 ₽ / 30 дней
        $pro = $this->tariff('pro');       // 2 990 ₽ / 30 дней

        $this->pay($tutor, $basic);
        $old = $tutor->activeSubscription();
        $this->travel(18)->days();

        // Осталось 12 дней: 1 490 × 12 / 30 = 596 ₽ → 596 / 2 990 × 30 = 5,98 → +5 дней
        Livewire::actingAs($tutor)->test(SubscriptionScreen::class)
            ->call('openSelect', $pro->id)
            ->assertSee('Остаток тарифа «Базовый» (12 дней) добавим к новому: +5 дней');

        $payment = $this->pay($tutor, $pro);
        $new = $tutor->fresh()->activeSubscription();

        $this->assertSame($pro->id, $new->tariff_id);
        $this->assertSame(Subscription::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertTrue($new->ends_at->eq(now()->addDays(35)));
        $this->assertEquals(['from' => $old->id, 'value' => 596, 'days' => 5], $payment->meta['carry_over']);

        // Лимит занятий по-прежнему считается 30-дневными циклами от начала новой подписки
        $this->assertTrue(SubscriptionService::periodResetsAt($tutor->fresh())->eq(now()->addDays(30)));
        $this->travel(31)->days();
        $this->assertTrue(SubscriptionService::periodStart($tutor->fresh())->eq($new->starts_at->copy()->addDays(30)));
        $this->assertTrue(SubscriptionService::periodResetsAt($tutor->fresh())->eq($new->ends_at));
    }

    public function test_downgrade_adds_unused_value_as_days_of_new_tariff(): void
    {
        $tutor = $this->tutor();
        $pro = $this->tariff('pro');
        $basic = $this->tariff('basic');

        $this->pay($tutor, $pro);
        $this->travel(10)->days();

        // Осталось 20 дней: 2 990 × 20 / 30 = 1 993,33 ₽ → 1 993,33 / 1 490 × 30 = 40,13 → +40 дней
        Livewire::actingAs($tutor)->test(SubscriptionScreen::class)
            ->call('openSelect', $basic->id)
            ->assertSee('Остаток тарифа «Профи» (20 дней) добавим к новому: +40 дней');

        $this->pay($tutor, $basic);

        $new = $tutor->fresh()->activeSubscription();
        $this->assertSame($basic->id, $new->tariff_id);
        $this->assertTrue($new->ends_at->eq(now()->addDays(70)));
    }

    public function test_extended_subscription_carries_all_paid_periods(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');
        $pro = $this->tariff('pro');

        // Два месяца «Базового» (60 дней за 2 980 ₽), прошло 15 дней:
        // 2 980 × 45 / 60 = 2 235 ₽ → 2 235 / 2 990 × 30 = 22,4 → +22 дня
        $this->pay($tutor, $basic);
        $this->pay($tutor, $basic);
        $this->travel(15)->days();

        $this->pay($tutor, $pro);

        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(52)));
    }

    public function test_same_tariff_renewal_extends_from_current_end(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');

        $this->pay($tutor, $basic);
        $subscription = $tutor->activeSubscription();
        $endsBefore = $subscription->ends_at->copy();
        $this->travel(20)->days();

        Livewire::actingAs($tutor)->test(SubscriptionScreen::class)
            ->call('openSelect', $basic->id, true)
            ->assertSee('Продление тарифа')
            ->assertDontSee('Остаток тарифа');

        $payment = $this->pay($tutor, $basic);

        $fresh = $tutor->fresh()->activeSubscription();
        $this->assertSame($subscription->id, $fresh->id);
        $this->assertTrue($fresh->ends_at->eq($endsBefore->addDays(30)));
        $this->assertArrayNotHasKey('carry_over', $payment->meta ?? []);
    }

    public function test_free_to_paid_has_no_carry_over(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');

        SubscriptionService::activate($tutor, $this->tariff('start'));
        $this->travel(5)->days();

        Livewire::actingAs($tutor)->test(SubscriptionScreen::class)
            ->call('openSelect', $basic->id)
            ->assertDontSee('Остаток тарифа');

        $payment = $this->pay($tutor, $basic);

        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(30)));
        $this->assertArrayNotHasKey('carry_over', $payment->meta ?? []);
    }

    public function test_complimentary_tariff_has_no_carry_over(): void
    {
        $tutor = $this->tutor();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid()]);

        SubscriptionService::assignByAdmin($tutor, $this->tariff('pro'), $admin, days: 30, free: true);
        $this->travel(5)->days();

        $this->pay($tutor, $this->tariff('basic'));

        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(30)));
    }

    public function test_refund_of_new_payment_keeps_carried_days(): void
    {
        $tutor = $this->tutor();
        $this->pay($tutor, $this->tariff('basic'));
        $this->travel(18)->days();
        $payment = $this->pay($tutor, $this->tariff('pro'));

        // Перенесённые 5 дней оплачены прошлым платежом — возврат нового снимает только его 30 дней
        $service = new AdminPaymentsService();
        $this->assertStringContainsString('30 дней', $service->refundEffect($payment));
        $service->refund($payment);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->ends_at->eq(now()->addDays(5)));
    }

    public function test_refund_of_old_payment_removes_carried_days(): void
    {
        $tutor = $this->tutor();
        $first = $this->pay($tutor, $this->tariff('basic'));
        $this->travel(18)->days();
        $this->pay($tutor, $this->tariff('pro'));

        // Деньги за прошлый тариф вернули целиком — перенесённые из него 5 дней не остаются в подарок
        $service = new AdminPaymentsService();
        $this->assertStringContainsString('5 дней', $service->refundEffect($first->fresh()));
        $service->refund($first->fresh());

        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(30)));
    }

    public function test_refund_of_yearly_payment_recounts_carried_days_at_monthly_price(): void
    {
        $tutor = $this->tutor();
        $pro = $this->tariff('pro');
        $pro->update(['yearly_price' => 29900]);

        $this->pay($tutor, $this->tariff('basic'));
        $this->travel(18)->days();

        // 596 ₽ по годовой цене: 596 / 29 900 × 365 = 7,27 → +7 дней
        $payment = $this->pay($tutor, $pro, 29900, 365);
        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(372)));

        // После возврата года остаток пересчитывается по месячной цене: 596 / 2 990 × 30 = 5,98 → 5 дней
        (new AdminPaymentsService())->refund($payment);

        $this->assertTrue($tutor->fresh()->activeSubscription()->ends_at->eq(now()->addDays(5)));
    }
}
