<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Payments;
use App\Models\ReferralReward;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Notifications\SubscriptionPaid;
use App\Notifications\SubscriptionRefunded;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Платежи (/cabinet/admin/payments): список, фильтры, подтверждение оплаты, возврат, подписки. */
class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
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

    private function admin(): User
    {
        return $this->user(User::ROLE_ADMIN, ['name' => 'Анна Куликова']);
    }

    private function tariff(string $slug): Tariff
    {
        return Tariff::where('slug', $slug)->firstOrFail();
    }

    private function payment(User $tutor, array $attrs = []): SubscriptionPayment
    {
        return SubscriptionPayment::create(array_merge([
            'user_id' => $tutor->id,
            'tariff_id' => $this->tariff('basic')->id,
            'amount' => 1490,
            'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'gateway' => 'yookassa',
        ], $attrs));
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/payments')->assertRedirect(route('login'));
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->actingAs($tutor)->get('/cabinet/admin/payments')->assertRedirect(\App\Http\Middleware\EnsureCabinetRole::homeFor($tutor));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/cabinet/admin/payments')->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->admin())->get('/cabinet/admin/payments')->assertOk()->assertSee('Платежи')->assertSee('Подписки');
    }

    public function test_list_month_line_statuses_and_queue(): void
    {
        $ivan = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов']);
        $olga = $this->user(User::ROLE_TUTOR, ['name' => 'Ольга Белова']);
        $this->payment($ivan, ['amount' => 2990, 'tariff_id' => $this->tariff('pro')->id]);
        $this->payment($ivan, ['amount' => 777, 'status' => SubscriptionPayment::STATUS_FAILED, 'paid_at' => null, 'meta' => ['status_response' => ['cancellation_details' => ['reason' => 'insufficient_funds']]]]);
        $stuck = $this->payment($olga, ['amount' => 1490, 'status' => SubscriptionPayment::STATUS_PENDING, 'paid_at' => null, 'gateway_order_id' => 'yk-stuck-000123']);
        $stuck->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $this->payment($olga, ['amount' => 1, 'status' => SubscriptionPayment::STATUS_REFUNDED, 'meta' => ['card_binding' => true]]);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->assertSee('оплачено на 2 990 ₽')
            ->assertSee('1 оплата')
            ->assertSee('Ожидают оплаты дольше суток')
            ->assertSee('ждёт 2 дня')
            ->assertSee('Иван Орлов')
            ->assertSee('Тариф «Профи»')
            ->assertSee('Не прошёл')
            ->assertSee('Привязка карты')
            ->assertSee('Не прошли · 1')
            ->set('status', SubscriptionPayment::STATUS_FAILED)
            ->assertSee('777 ₽')
            ->assertDontSee('font-medium">2 990 ₽', false)
            ->set('status', 'all')
            ->call('setPurpose', 'card')
            ->assertSee('Привязка карты')
            ->assertDontSee('777 ₽')
            ->call('resetFilters')
            ->set('q', 'Ольга')
            ->assertDontSee('777 ₽');
    }

    public function test_details_hide_technical_fields_and_show_failure_reason(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR, ['name' => 'Дмитрий Лебедев']);
        $p = $this->payment($tutor, ['status' => SubscriptionPayment::STATUS_FAILED, 'paid_at' => null, 'gateway_order_id' => 'abc-123456',
            'meta' => ['auto_renew' => true, 'status_response' => ['cancellation_details' => ['reason' => 'insufficient_funds'], 'payment_method' => ['type' => 'bank_card', 'card' => ['last4' => '9087']]]]]);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->call('openPayment', $p->id)
            ->assertSee('Банк отклонил списание: недостаточно средств')
            ->assertSee('Карта •••• 9087, автопродление')
            ->assertSee('abc-123456')
            ->assertSee('Закрыть')
            ->assertDontSee('Оформить возврат');
    }

    public function test_confirm_requires_checkbox_and_activates_tariff(): void
    {
        Notification::fake();
        $tutor = $this->user(User::ROLE_TUTOR);
        $admin = $this->admin();
        $p = $this->payment($tutor, ['status' => SubscriptionPayment::STATUS_PENDING, 'paid_at' => null, 'gateway_order_id' => 'yk-000000abcdef']);

        $c = Livewire::actingAs($admin)->test(Payments::class)
            ->call('openPayment', $p->id)
            ->call('openConfirm')
            ->assertSee('Подтверждайте, только если оплата видна в личном кабинете ЮKassa')
            ->assertSee('Платёж …abcdef')
            ->call('confirmPayment');
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $p->fresh()->status);

        $c->set('checked', true)->call('confirmPayment')->assertDispatched('toast', message: 'Оплата подтверждена · тариф «Базовый» подключён');

        $p->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $p->status);
        $this->assertSame('Анна Куликова', $p->meta['confirmed_by']);
        $this->assertSame($this->tariff('basic')->id, $tutor->fresh()->activeSubscription()->tariff_id);
        Notification::assertSentTo($tutor, SubscriptionPaid::class);
    }

    public function test_card_binding_cannot_be_confirmed(): void
    {
        $tutor = $this->user(User::ROLE_TUTOR);
        $p = $this->payment($tutor, ['amount' => 1, 'status' => SubscriptionPayment::STATUS_PENDING, 'paid_at' => null, 'meta' => ['card_binding' => true]]);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->call('openPayment', $p->id)
            ->assertDontSee('Подтвердить оплату')
            ->call('openConfirm')
            ->assertSet('modal', 'details');
    }

    public function test_refund_through_yookassa_shortens_subscription_and_notifies(): void
    {
        Notification::fake();
        Http::fake(['api.yookassa.ru/v3/refunds' => Http::response(['id' => 'r1', 'status' => 'succeeded'])]);
        $tutor = $this->user(User::ROLE_TUTOR);
        $p = $this->payment($tutor, ['gateway_order_id' => 'yk-pay-1', 'amount' => 2990, 'tariff_id' => $this->tariff('pro')->id]);
        $sub = SubscriptionService::activate($tutor, $this->tariff('pro'), $p, days: 60);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->call('openPayment', $p->id)
            ->assertSee('Оформить возврат')
            ->call('openRefund')
            ->assertSee('Отменить возврат нельзя')
            ->assertSee('сократится до')
            ->call('refundPayment')
            ->assertDispatched('toast');

        $p->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_REFUNDED, $p->status);
        $this->assertSame('Анна Куликова', $p->meta['refunded_by']);
        $this->assertTrue($sub->fresh()->ends_at->lt(now()->addDays(31)));
        Notification::assertSentTo($tutor, SubscriptionRefunded::class);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'refunds') && $r['payment_id'] === 'yk-pay-1');
    }

    public function test_refund_error_keeps_payment_paid(): void
    {
        Http::fake(['api.yookassa.ru/v3/refunds' => Http::response(['type' => 'error', 'description' => 'Недостаточно денег на балансе магазина'], 400)]);
        $tutor = $this->user(User::ROLE_TUTOR);
        $p = $this->payment($tutor, ['gateway_order_id' => 'yk-pay-2']);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->call('openPayment', $p->id)
            ->call('openRefund')
            ->call('refundPayment')
            ->assertDispatched('toast', tone: 'danger');

        $this->assertSame(SubscriptionPayment::STATUS_PAID, $p->fresh()->status);
    }

    public function test_refund_of_extra_lessons_and_referral_bonus(): void
    {
        Notification::fake();
        $referrer = $this->user(User::ROLE_TUTOR, ['extra_lessons_balance' => 10]);
        $tutor = $this->user(User::ROLE_TUTOR, ['extra_lessons_balance' => 14]);
        $extra = $this->payment($tutor, ['extra_lessons' => 10, 'amount' => 1000, 'period_days' => 0]);
        $paid = $this->payment($tutor);
        ReferralReward::create(['referrer_id' => $referrer->id, 'referred_id' => $tutor->id, 'payment_id' => $paid->id, 'referrer_lessons' => 10, 'referred_lessons' => 0, 'status' => ReferralReward::STATUS_CREDITED]);

        $c = Livewire::actingAs($this->admin())->test(Payments::class)
            ->call('openPayment', $extra->id)->call('openRefund')
            ->assertSee('с баланса учителя — останется 4')
            ->call('refundPayment')
            ->assertDispatched('toast', message: 'Возврат оформлен · 10 занятий списаны');
        $this->assertSame(4, $tutor->fresh()->extra_lessons_balance);

        $c->call('openPayment', $paid->id)->call('openRefund')
            ->assertSee('Бонусы партнёрской программы за этот платёж спишутся')
            ->call('refundPayment');
        $this->assertSame(0, $referrer->fresh()->extra_lessons_balance);
        $this->assertSame(ReferralReward::STATUS_REVOKED, ReferralReward::first()->status);
    }

    public function test_subscriptions_tab_segments(): void
    {
        $maria = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $andrey = $this->user(User::ROLE_TUTOR, ['name' => 'Андрей Громов']);
        $elena = $this->user(User::ROLE_TUTOR, ['name' => 'Елена Воронова']);
        $dmitry = $this->user(User::ROLE_TUTOR, ['name' => 'Дмитрий Лебедев']);
        SubscriptionService::activate($maria, $this->tariff('pro'), days: 20);
        SubscriptionService::activate($andrey, $this->tariff('pro'), days: 1);
        SubscriptionService::activate($elena, $this->tariff('master'), unlimited: true, price: 0);
        Subscription::create(['user_id' => $dmitry->id, 'tariff_id' => $this->tariff('master')->id, 'status' => Subscription::STATUS_EXPIRED, 'price' => 6900, 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);

        Livewire::actingAs($this->admin())->test(Payments::class)
            ->set('tab', 'subscriptions')
            ->assertSee('Действующие · 3')
            ->assertSee('Заканчиваются за неделю · 1')
            ->assertSee('Предоставлены бесплатно · 1')
            ->assertSee('Мария Соколова')
            ->assertSee('закончится завтра')
            ->assertSee('вместо 6 900 ₽ в месяц')
            ->assertDontSee('Дмитрий Лебедев')
            ->set('sub', 'ended')
            ->assertSee('Дмитрий Лебедев')
            ->assertSee('закончился вчера')
            ->assertDontSee('Мария Соколова')
            ->assertSee('Назначить или изменить тариф можно в карточке учителя.');
    }
}
