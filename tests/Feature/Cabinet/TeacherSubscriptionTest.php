<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Subscription;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Тариф и платежи учителя в новом кабинете (/cabinet/teacher/subscription). */
class TeacherSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
    }

    private function tutor(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    private function yookassa(array $response): void
    {
        Setting::updateOrCreate(['key' => 'yookassa_shop_id'], ['value' => '123']);
        Setting::updateOrCreate(['key' => 'yookassa_secret_key'], ['value' => 'test_key']);
        Http::fake(['api.yookassa.ru/*' => Http::response($response)]);
    }

    private function tariff(string $slug): Tariff
    {
        return Tariff::where('slug', $slug)->first();
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.subscription'))->assertRedirect();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true]);
        $this->actingAs($student)->get(route('cabinet.teacher.subscription'))->assertRedirect(route('cabinet.student.home'));
    }

    public function test_without_subscription_offers_free_tariff(): void
    {
        $this->actingAs($this->tutor())->get(route('cabinet.teacher.subscription'))
            ->assertOk()
            ->assertSee('Тариф не выбран')
            ->assertSee('Выбрать тариф')
            ->assertSee('Подключить')
            ->assertSee('Оплатить и подключить');
    }

    public function test_activate_free_tariff(): void
    {
        $tutor = $this->tutor();
        $free = $this->tariff('start');

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->call('openSelect', $free->id)
            ->assertSee('Смена тарифа')
            ->call('confirmSelect')
            ->assertDispatched('toast')
            ->assertSet('selectTariffId', null);

        $this->assertSame($free->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_renew_button_only_in_window_and_not_for_complimentary(): void
    {
        $tutor = $this->tutor();
        SubscriptionService::activate($tutor, $this->tariff('basic'));

        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription'))
            ->assertOk()
            ->assertSee('Тариф «Базовый»')
            ->assertDontSee('Продлить');

        $tutor->activeSubscription()->update(['ends_at' => now()->addDays(10)]);
        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription'))->assertSee('Продлить');

        $other = $this->tutor();
        SubscriptionService::activate($other, $this->tariff('master'), unlimited: true, price: 0);
        $this->actingAs($other)->get(route('cabinet.teacher.subscription'))
            ->assertSee('Предоставлен бесплатно')
            ->assertSee('Оплата не требуется')
            ->assertDontSee('Продлить');
    }

    /** Способы оплаты — с логотипами платёжных систем; сохранённый способ — с логотипом своего типа. */
    public function test_payment_methods_show_logos(): void
    {
        $this->yookassa(['id' => 'yk-0', 'status' => 'pending']);
        $tutor = $this->tutor(['yookassa_payment_method_id' => 'pm-sbp', 'payment_method_title' => 'СБП']);
        SubscriptionPayment::create([
            'user_id' => $tutor->id, 'tariff_id' => $this->tariff('basic')->id, 'amount' => 990,
            'status' => SubscriptionPayment::STATUS_PAID, 'gateway' => 'yookassa', 'gateway_order_id' => 'yk-saved', 'paid_at' => now(),
            'meta' => ['status_response' => ['payment_method' => ['id' => 'pm-sbp', 'type' => 'sbp', 'saved' => true]]],
        ]);

        $page = Livewire::actingAs($tutor)->test(Subscription::class);
        // Сохранённый способ — логотип СБП вместо значка кошелька
        $page->assertSeeHtml(asset('images/payment/sbp.svg'));

        // Выбор способа при оплате (без сохранённого способа)
        $page = Livewire::actingAs($this->tutor())->test(Subscription::class)->call('openSelect', $this->tariff('basic')->id);
        foreach (['sbp.svg', 'sberpay.svg', 'tpay.svg', 'card.svg', 'yoomoney.png'] as $logo) {
            $page->assertSeeHtml(asset('images/payment/' . $logo));
        }
    }

    public function test_paid_tariff_redirects_to_payment_page_with_chosen_method(): void
    {
        $this->yookassa(['id' => 'yk-1', 'status' => 'pending', 'confirmation' => ['confirmation_url' => 'https://pay.test/go']]);
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');
        $basic->update(['yearly_price' => $basic->price * 10]);

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->set('billingPeriod', 'year')
            ->call('openSelect', $basic->id)
            ->assertSee('Способ оплаты')
            ->set('payMethod', 'bank_card')
            ->call('confirmSelect')
            ->assertRedirect('https://pay.test/go');

        $payment = SubscriptionPayment::where('user_id', $tutor->id)->first();
        $this->assertSame(365, (int) $payment->period_days);
        $this->assertEquals($basic->yearly_price, (float) $payment->amount);
        Http::assertSent(fn ($request) => ($request['payment_method_data']['type'] ?? null) === 'bank_card');
    }

    public function test_one_click_payment_with_saved_card(): void
    {
        Notification::fake();
        $this->yookassa(['id' => 'yk-2', 'status' => 'succeeded']);
        $tutor = $this->tutor(['yookassa_payment_method_id' => 'pm-1', 'payment_method_title' => 'Bank card *4477']);
        $basic = $this->tariff('basic');

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->call('openSelect', $basic->id)
            ->assertDontSee('SberPay')
            ->call('confirmSelect')
            ->assertDispatched('toast', message: 'Оплачено — тариф «Базовый» подключён');

        $this->assertSame($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_downgrade_to_free_is_scheduled(): void
    {
        $tutor = $this->tutor();
        SubscriptionService::activate($tutor, $this->tariff('basic'));
        $free = $this->tariff('start');

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->call('openSelect', $free->id)
            ->assertSee('после окончания оплаченного периода')
            ->call('confirmSelect')
            ->assertDispatched('toast');

        $this->assertSame($free->id, $tutor->fresh()->scheduledSubscription()->tariff_id);
        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription'))->assertSee('Начнёт действовать');
    }

    public function test_paid_tariff_without_yookassa_shows_error_in_window(): void
    {
        $tutor = $this->tutor();

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->call('openSelect', $this->tariff('basic')->id)
            ->call('confirmSelect')
            ->assertSet('selectTariffId', $this->tariff('basic')->id)
            ->assertSee('Онлайн-оплата подключается');

        $this->assertNull($tutor->fresh()->activeSubscription());
    }

    public function test_buy_extra_lessons(): void
    {
        Notification::fake();
        $this->yookassa(['id' => 'yk-3', 'status' => 'succeeded']);
        $tutor = $this->tutor(['yookassa_payment_method_id' => 'pm-1']);
        SubscriptionService::activate($tutor, $this->tariff('basic'));

        // ?buy=1 сразу открывает окно
        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription', ['buy' => 1]))
            ->assertSee('Дополнительные занятия')
            ->assertSee('Сколько занятий докупить');

        Livewire::actingAs($tutor)->test(Subscription::class)
            ->call('openBuy')
            ->set('quantity', SubscriptionService::extraLessonsMax() + 1)
            ->call('confirmBuy')
            ->assertHasErrors(['quantity' => 'max'])
            ->set('quantity', 3)
            ->call('confirmBuy')
            ->assertHasNoErrors()
            ->assertSet('buyOpen', false)
            ->assertDispatched('toast');

        $this->assertSame(3, $tutor->fresh()->extra_lessons_balance);
    }

    public function test_card_auto_renew_and_unbind(): void
    {
        $tutor = $this->tutor(['yookassa_payment_method_id' => 'pm-1', 'payment_method_title' => 'Bank card *4477']);
        SubscriptionService::activate($tutor, $this->tariff('basic'));

        $c = Livewire::actingAs($tutor)->test(Subscription::class)
            ->assertSee('Bank card *4477')
            ->call('toggleAutoRenew')
            ->assertDispatched('toast', message: 'Автопродление включено');
        $this->assertTrue($tutor->fresh()->auto_renew);

        $c->set('removeOpen', true)
            ->assertSee('Отвязать способ оплаты?')
            ->call('confirmRemove');

        $tutor->refresh();
        $this->assertNull($tutor->yookassa_payment_method_id);
        $this->assertFalse($tutor->auto_renew);
    }

    public function test_payment_history_hides_failed_and_links_receipt(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');
        SubscriptionService::activate($tutor, $basic);

        $paid = SubscriptionPayment::create(['user_id' => $tutor->id, 'tariff_id' => $basic->id, 'amount' => 1490, 'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_PAID, 'gateway' => 'yookassa', 'paid_at' => now()]);
        SubscriptionPayment::create(['user_id' => $tutor->id, 'tariff_id' => $basic->id, 'amount' => 1, 'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_REFUNDED, 'gateway' => 'yookassa', 'meta' => ['card_binding' => true]]);
        SubscriptionPayment::create(['user_id' => $tutor->id, 'tariff_id' => $basic->id, 'amount' => 777, 'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_FAILED, 'gateway' => 'yookassa']);

        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription'))
            ->assertOk()
            ->assertSee('История платежей')
            ->assertSee(route('subscription.payment.receipt', $paid))
            ->assertSee('Привязка способа оплаты')
            ->assertSee('Проверочный 1 ₽ возвращён')
            ->assertDontSee('777 ₽');
    }

    public function test_all_payments_link_leads_to_payments_screen(): void
    {
        $tutor = $this->tutor();
        $basic = $this->tariff('basic');
        SubscriptionPayment::create(['user_id' => $tutor->id, 'tariff_id' => $basic->id, 'amount' => 1490, 'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_PAID, 'gateway' => 'yookassa', 'paid_at' => now()]);

        $this->actingAs($tutor)->get(route('cabinet.teacher.subscription'))
            ->assertOk()
            ->assertSee('Все платежи')
            ->assertSee(route('cabinet.teacher.payments'), false);
    }
}
