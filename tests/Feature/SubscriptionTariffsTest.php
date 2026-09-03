<?php

namespace Tests\Feature;

use App\Filament\App\Pages\ManageSubscription;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionTariffsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
    }

    protected function makeTutor(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'username' => 'admin' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    public function test_public_tariffs_page_renders(): void
    {
        $this->get('/tariffs')
            ->assertOk()
            ->assertSee('Старт')
            ->assertSee('Профи')
            ->assertSee('Гарантийные условия');
    }

    public function test_public_offer_page_renders(): void
    {
        $this->get('/offer')
            ->assertOk()
            ->assertSee('Публичная оферта')
            ->assertSee('Возврат денежных средств')
            // тарифы и их характеристики — из БД
            ->assertSee('Тариф «Профи»')
            ->assertSee('2 990 ₽')
            ->assertSee('предоставляется бесплатно')
            ->assertSee('До 12 участников в занятии')
            ->assertSee('120 занятий в месяц')
            ->assertSee('Подписка оформляется на 30 календарных дней')
            // значения по умолчанию из OfferSettings
            ->assertSee('14 календарных дней')
            ->assertSee('10 рабочих дней')
            ->assertSee('ЮKassa')
            ->assertSee('info@serdal.ru')
            ->assertSee('Для образовательных центров (B2B)')
            ->assertDontSee('Редакция от');
    }

    public function test_public_offer_page_uses_admin_settings(): void
    {
        foreach ([
            'legal_name' => 'ИП Тестов Тест Тестович',
            'legal_inn' => '123456789012',
            'legal_email' => 'billing@example.com',
            'offer_edition_date' => '2026-09-03',
            'offer_payment_provider' => 'CloudPayments',
            'offer_payment_methods' => 'банковской картой',
            'offer_refund_days' => '21',
            'offer_refund_processing_days' => '3',
            'b2b_enabled' => '0',
        ] as $key => $value) {
            \App\Models\Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->get('/offer')
            ->assertOk()
            ->assertSee('ИП Тестов Тест Тестович')
            ->assertSee('ИНН: 123456789012')
            ->assertSee('billing@example.com')
            ->assertSee('Редакция от 03.09.2026')
            ->assertSee('CloudPayments')
            ->assertSee('21 календарного дня')
            ->assertSee('3 рабочих дней')
            ->assertDontSee('ЮKassa')
            ->assertDontSee('info@serdal.ru')
            ->assertDontSee('Для образовательных центров (B2B)');

        $this->get('/tariffs')
            ->assertOk()
            ->assertSee('21 день на возврат')
            ->assertSee('CloudPayments')
            ->assertSee('billing@example.com');
    }

    public function test_admin_tariff_pages_render(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/tariffs')->assertOk();
        $this->actingAs($admin)->get('/admin/subscriptions')->assertOk();
        $this->actingAs($admin)->get('/admin/subscription-payments')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_tutor_subscription_page_renders(): void
    {
        $tutor = $this->makeTutor();

        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertSee('Тарифы');
    }

    public function test_tutor_can_activate_free_tariff(): void
    {
        $tutor = $this->makeTutor();
        $freeTariff = Tariff::where('slug', 'start')->first();

        Livewire::actingAs($tutor)
            ->test(ManageSubscription::class)
            ->call('selectTariff', $freeTariff->id);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertNotNull($subscription);
        $this->assertEquals($freeTariff->id, $subscription->tariff_id);
        $this->assertNull($subscription->ends_at);
    }

    public function test_complimentary_subscription_hides_price_and_renew_button(): void
    {
        $tutor = $this->makeTutor();
        $master = Tariff::where('slug', 'master')->first();

        SubscriptionService::activate($tutor, $master, unlimited: true, price: 0);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertTrue($subscription->isComplimentary());

        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertSee('Предоставлен бесплатно')
            ->assertSee('Оплата не требуется')
            ->assertDontSee('Продлить');
    }

    public function test_yearly_payment_extends_subscription_for_a_year(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        $basic->update(['yearly_price' => $basic->price * 10]);

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->yearly_price,
            'period_days' => 365,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
        ]);

        SubscriptionService::applyPaidPayment($payment);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertTrue($subscription->ends_at->isSameDay(now()->addDays(365)));
        // Месячный лимит занятий действует и на годовой подписке
        $this->assertEquals(0, SubscriptionService::lessonsUsedThisPeriod($tutor->fresh()));
    }

    public function test_renew_button_hidden_until_expiry_window(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());

        // Сразу после оплаты (30 дней до конца) кнопки «Продлить» нет
        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertDontSee('Продлить');

        // За 10 дней до окончания — появляется
        $tutor->activeSubscription()->update(['ends_at' => now()->addDays(10)]);
        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertSee('Продлить');
    }

    public function test_saved_payment_method_enables_auto_renew_after_payment(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'yk-save-1',
                'status' => 'succeeded',
                'payment_method' => ['id' => 'pm-123', 'saved' => true, 'title' => 'Bank card *4477'],
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-save-1',
            'meta' => ['save_method' => true, 'auto_renew_opt_in' => true],
        ]);

        $this->actingAs($tutor)->get(route('subscription.payment.return', $payment));

        $tutor = $tutor->fresh();
        $this->assertEquals('pm-123', $tutor->yookassa_payment_method_id);
        $this->assertEquals('Bank card *4477', $tutor->payment_method_title);
        $this->assertTrue($tutor->auto_renew);
    }

    public function test_saving_card_without_opt_in_keeps_auto_renew_off(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'yk-save-2',
                'status' => 'succeeded',
                'payment_method' => ['id' => 'pm-456', 'saved' => true, 'title' => 'Bank card *1111'],
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-save-2',
            'meta' => ['save_method' => true, 'auto_renew_opt_in' => false],
        ]);

        $this->actingAs($tutor)->get(route('subscription.payment.return', $payment));

        $tutor = $tutor->fresh();
        $this->assertEquals('pm-456', $tutor->yookassa_payment_method_id);
        $this->assertFalse($tutor->auto_renew);
    }

    public function test_card_binding_saves_method_and_refunds_without_touching_subscription(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/v3/refunds' => \Illuminate\Support\Facades\Http::response(['id' => 'r-1', 'status' => 'succeeded']),
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'yk-bind-1',
                'status' => 'succeeded',
                'payment_method' => ['id' => 'pm-bind', 'saved' => true, 'title' => 'Bank card *2222'],
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        SubscriptionService::activate($tutor, $basic);
        $endsAt = $tutor->activeSubscription()->ends_at;

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => 1,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-bind-1',
            'meta' => ['card_binding' => true, 'save_method' => true],
        ]);

        $this->actingAs($tutor)->get(route('subscription.payment.return', $payment));

        $tutor = $tutor->fresh();
        $this->assertEquals('pm-bind', $tutor->yookassa_payment_method_id);
        $this->assertFalse($tutor->auto_renew);
        // Рубль возвращён, подписка не продлилась
        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertTrue($tutor->activeSubscription()->ends_at->equalTo($endsAt));
    }

    public function test_one_click_payment_with_saved_card(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response(['id' => 'yk-click-1', 'status' => 'succeeded']),
        ]);

        \App\Models\Setting::updateOrCreate(['key' => 'yookassa_shop_id'], ['value' => '123']);
        \App\Models\Setting::updateOrCreate(['key' => 'yookassa_secret_key'], ['value' => 'test_key']);

        $tutor = $this->makeTutor();
        $tutor->update(['yookassa_payment_method_id' => 'pm-123', 'payment_method_title' => 'Bank card *4477']);
        $basic = Tariff::where('slug', 'basic')->first();

        Livewire::actingAs($tutor)
            ->test(ManageSubscription::class)
            ->call('selectTariff', $basic->id);

        // Подписка активирована сразу, без ухода на платёжную страницу
        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
        $this->assertEquals(
            \App\Models\SubscriptionPayment::STATUS_PAID,
            \App\Models\SubscriptionPayment::where('user_id', $tutor->id)->latest()->first()->status
        );
    }

    public function test_auto_renewal_charges_saved_card_and_extends(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'yk-auto-1',
                'status' => 'succeeded',
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        SubscriptionService::activate($tutor, $basic);
        $tutor->update(['auto_renew' => true, 'yookassa_payment_method_id' => 'pm-123']);
        $tutor->activeSubscription()->update(['ends_at' => now()->addHours(12)]);
        $endsAt = $tutor->activeSubscription()->ends_at;

        $this->artisan('subscriptions:check')->assertSuccessful();

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertTrue($subscription->ends_at->isSameDay($endsAt->copy()->addDays(30)));

        $autoPayment = \App\Models\SubscriptionPayment::where('user_id', $tutor->id)->latest()->first();
        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_PAID, $autoPayment->status);
        $this->assertTrue((bool) ($autoPayment->meta['auto_renew'] ?? false));

        // Повторный запуск не создаёт второго списания
        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->assertEquals(1, \App\Models\SubscriptionPayment::where('user_id', $tutor->id)->count());
    }

    public function test_failed_auto_renewal_notifies_teacher(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'yk-auto-2',
                'status' => 'canceled',
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        SubscriptionService::activate($tutor, $basic);
        $tutor->update(['auto_renew' => true, 'yookassa_payment_method_id' => 'pm-123']);
        $tutor->activeSubscription()->update(['ends_at' => now()->addHours(12)]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo($tutor, \App\Notifications\SubscriptionAutoRenewFailed::class);
        $this->assertEquals(
            \App\Models\SubscriptionPayment::STATUS_FAILED,
            \App\Models\SubscriptionPayment::where('user_id', $tutor->id)->latest()->first()->status
        );
    }

    public function test_admin_refund_calls_yookassa_api(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/v3/refunds' => \Illuminate\Support\Facades\Http::response([
                'id' => 'refund-1',
                'status' => 'succeeded',
            ]),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PAID,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-refund-1',
            'paid_at' => now(),
        ]);

        $this->assertTrue(\App\Services\YooKassaService::refundPayment($payment));
        \Illuminate\Support\Facades\Http::assertSent(
            fn($request) => str_contains($request->url(), '/v3/refunds')
                && $request['payment_id'] === 'yk-refund-1'
                && $request['amount']['value'] === number_format($basic->price, 2, '.', '')
        );
    }

    public function test_stale_pending_payment_is_marked_failed(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response(['id' => 'yk-stale', 'status' => 'canceled']),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        // Ушёл на оплату и не вернулся
        $abandoned = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-stale',
        ]);
        $abandoned->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('subscriptions:check')->assertSuccessful();

        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_FAILED, $abandoned->fresh()->status);
    }

    public function test_failed_payments_hidden_from_teacher_history(): void
    {
        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_FAILED,
            'gateway' => 'yookassa',
        ]);
        // Свежий незавершённый — виден и предлагает вернуться к оплате
        \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'payment_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=test',
        ]);

        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertDontSee('Не прошёл')
            ->assertSee('ссылка действует ещё')
            ->assertSee('https://yoomoney.ru/checkout/payments/v2/contract?orderId=test');
    }

    public function test_admin_refund_notifies_teacher_and_shows_in_history(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/v3/refunds' => \Illuminate\Support\Facades\Http::response(['id' => 'r-2', 'status' => 'succeeded']),
        ]);

        $admin = $this->makeAdmin();
        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PAID,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-refund-2',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments::class)
            ->callTableAction('refund', $payment);

        $payment = $payment->fresh();
        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_REFUNDED, $payment->status);
        $this->assertNotEmpty($payment->meta['refunded_at']);

        \Illuminate\Support\Facades\Notification::assertSentTo($tutor, \App\Notifications\SubscriptionRefunded::class);

        // Учитель видит информацию о возврате в истории платежей
        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertSee('Возврат оформлен')
            ->assertSee('рабочих дней');
    }

    public function test_refund_shortens_subscription_by_paid_period(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/v3/refunds' => \Illuminate\Support\Facades\Http::response(['id' => 'r-3', 'status' => 'succeeded']),
        ]);

        $admin = $this->makeAdmin();
        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        // Две оплаты по месяцу: подписка на 60 дней
        $first = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'period_days' => 30,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-r-a',
        ]);
        SubscriptionService::applyPaidPayment($first);
        $second = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'period_days' => 30,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-r-b',
        ]);
        SubscriptionService::applyPaidPayment($second);

        $endsBefore = $tutor->fresh()->activeSubscription()->ends_at;

        // Возврат второго платежа: минус 30 дней, подписка остаётся активной
        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments::class)
            ->callTableAction('refund', $second);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->ends_at->isSameDay($endsBefore->copy()->subDays(30)));

        // Возврат первого платежа: срока не остаётся — подписка завершается
        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments::class)
            ->callTableAction('refund', $first->fresh());

        $this->assertNull($tutor->fresh()->activeSubscription());
    }

    public function test_paid_subscription_is_not_complimentary(): void
    {
        $tutor = $this->makeTutor();
        $master = Tariff::where('slug', 'master')->first();

        SubscriptionService::activate($tutor, $master);

        $this->assertFalse($tutor->fresh()->activeSubscription()->isComplimentary());
    }

    protected function makeRoom(User $tutor): \App\Models\Room
    {
        return \App\Models\Room::create([
            'user_id' => $tutor->id,
            'name' => 'Тестовая комната',
            'meeting_id' => 'test-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
    }

    protected function makeCompletedLesson(User $tutor, \App\Models\Room $room): void
    {
        \App\Models\MeetingSession::create([
            'user_id' => $tutor->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(10),
            'status' => 'completed',
            'participant_count' => 2,
        ]);
    }

    public function test_lesson_start_blocked_without_subscription(): void
    {
        $tutor = $this->makeTutor();
        $room = $this->makeRoom($tutor);

        $this->assertNotNull(SubscriptionService::canStartLesson($tutor));

        $this->actingAs($tutor)
            ->get(route('rooms.start', $room))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_lesson_start_blocked_when_lessons_limit_reached(): void
    {
        $tutor = $this->makeTutor();
        $start = Tariff::where('slug', 'start')->first(); // 8 занятий в месяц

        SubscriptionService::activate($tutor, $start);
        $tutor->activeSubscription()->update(['starts_at' => now()->subDay()]);

        $room = $this->makeRoom($tutor);

        for ($i = 0; $i < $start->lessons_per_month; $i++) {
            $this->makeCompletedLesson($tutor, $room);
        }

        SubscriptionService::flushCanStartCache();
        $error = SubscriptionService::canStartLesson($tutor->fresh());
        $this->assertNotNull($error);
        $this->assertStringContainsString('исчерпан', $error);

        $this->actingAs($tutor)
            ->get(route('rooms.start', $room))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_lesson_start_allowed_within_limits(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'start')->first());

        $this->assertNull(SubscriptionService::canStartLesson($tutor->fresh()));
    }

    public function test_switch_to_free_tariff_is_deferred_until_paid_period_ends(): void
    {
        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        $free = Tariff::where('slug', 'start')->first();

        SubscriptionService::activate($tutor, $basic);
        $paidEndsAt = $tutor->activeSubscription()->ends_at;

        Livewire::actingAs($tutor)
            ->test(ManageSubscription::class)
            ->call('selectTariff', $free->id);

        // Платный тариф продолжает действовать, бесплатный — запланирован на дату окончания
        $tutor = $tutor->fresh();
        $this->assertEquals($basic->id, $tutor->activeSubscription()->tariff_id);
        $scheduled = $tutor->scheduledSubscription();
        $this->assertNotNull($scheduled);
        $this->assertEquals($free->id, $scheduled->tariff_id);
        $this->assertTrue($scheduled->starts_at->equalTo($paidEndsAt));

        // После окончания оплаченного периода активным становится бесплатный
        $this->travelTo($paidEndsAt->copy()->addMinute());
        $this->assertEquals($free->id, $tutor->fresh()->activeSubscription()->tariff_id);
        $this->travelBack();
    }

    public function test_switch_to_free_is_immediate_when_no_paid_period(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'master')->first(), unlimited: true);

        $free = Tariff::where('slug', 'start')->first();

        Livewire::actingAs($tutor)
            ->test(ManageSubscription::class)
            ->call('selectTariff', $free->id);

        // Бессрочная подписка не имеет оплаченного периода — переключение сразу
        $this->assertEquals($free->id, $tutor->fresh()->activeSubscription()->tariff_id);
        $this->assertNull($tutor->fresh()->scheduledSubscription());
    }

    public function test_no_expired_notification_when_downgrade_scheduled(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();
        $free = Tariff::where('slug', 'start')->first();

        SubscriptionService::activate($tutor, $basic);
        $endsAt = $tutor->activeSubscription()->ends_at;
        SubscriptionService::scheduleTariffChange($tutor, $free, $endsAt);

        $this->travelTo($endsAt->copy()->addHour());
        $this->artisan('subscriptions:check')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertNotSentTo(
            $tutor,
            \App\Notifications\SubscriptionExpired::class
        );
        $this->assertEquals($free->id, $tutor->fresh()->activeSubscription()->tariff_id);
        $this->travelBack();
    }

    public function test_expired_subscription_shows_renew_state(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());
        $endsAt = $tutor->activeSubscription()->ends_at;

        $this->travelTo($endsAt->copy()->addDay());
        SubscriptionService::flushCanStartCache();

        $this->assertNull($tutor->fresh()->activeSubscription());

        $this->actingAs($tutor)->get('/tutor/subscription')
            ->assertOk()
            ->assertSee('Срок истёк')
            ->assertSee('Продлить');

        $this->travelBack();
    }

    public function test_payment_history_page_and_receipt(): void
    {
        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PAID,
            'gateway' => 'yookassa',
            'paid_at' => now(),
        ]);
        $pending = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
        ]);

        // Страница истории платежей
        $this->actingAs($tutor)->get('/tutor/payments')->assertOk();

        // Квитанция по оплаченному платежу — доступна владельцу
        $this->actingAs($tutor)->get(route('subscription.payment.receipt', $payment))
            ->assertOk()
            ->assertSee('Квитанция об оплате № ' . $payment->id)
            ->assertSee($basic->name)
            ->assertSee('1 490');

        // По неоплаченному — 404
        $this->actingAs($tutor)->get(route('subscription.payment.receipt', $pending))->assertNotFound();

        // Чужому пользователю — 403
        $other = $this->makeTutor();
        $this->actingAs($other)->get(route('subscription.payment.receipt', $payment))->assertForbidden();
    }

    public function test_meeting_limits_follow_tariff(): void
    {
        $tutor = $this->makeTutor();

        // Без подписки: лимитов тарифа нет, запись запрещена
        $limits = SubscriptionService::meetingLimits($tutor);
        $this->assertNull($limits['max_participants']);
        $this->assertFalse($limits['record_allowed']);

        // «Старт»: 2 участника, 60 минут, записи недоступны
        SubscriptionService::activate($tutor, Tariff::where('slug', 'start')->first());
        $limits = SubscriptionService::meetingLimits($tutor->fresh());
        $this->assertEquals(2, $limits['max_participants']);
        $this->assertEquals(60, $limits['duration_minutes']);
        $this->assertFalse($limits['record_allowed']);

        // «Мастер»: 25 участников, без лимита длительности, записи доступны
        SubscriptionService::activate($tutor->fresh(), Tariff::where('slug', 'master')->first());
        $limits = SubscriptionService::meetingLimits($tutor->fresh());
        $this->assertEquals(25, $limits['max_participants']);
        $this->assertNull($limits['duration_minutes']);
        $this->assertTrue($limits['record_allowed']);
    }

    public function test_recordings_cleanup_respects_tariff_retention(): void
    {
        // «Базовый»: хранение записей 14 дней
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());
        $room = $this->makeRoom($tutor);

        $old = \App\Models\Recording::create([
            'meeting_id' => $room->meeting_id,
            'record_id' => 'old-' . uniqid(),
            'name' => 'Старая запись',
            'end_time' => now()->subDays(20),
        ]);
        $fresh = \App\Models\Recording::create([
            'meeting_id' => $room->meeting_id,
            'record_id' => 'fresh-' . uniqid(),
            'name' => 'Свежая запись',
            'end_time' => now()->subDays(5),
        ]);

        // Пользователь без активной подписки: записи не трогаем
        $noSub = $this->makeTutor();
        $noSubRoom = $this->makeRoom($noSub);
        $noSubRecording = \App\Models\Recording::create([
            'meeting_id' => $noSubRoom->meeting_id,
            'record_id' => 'nosub-' . uniqid(),
            'name' => 'Запись без подписки',
            'end_time' => now()->subDays(400),
        ]);

        $this->artisan('recordings:cleanup')->assertSuccessful();

        $this->assertSoftDeleted('recordings', ['id' => $old->id]);
        $this->assertNull($fresh->fresh()->deleted_at);
        $this->assertNull($noSubRecording->fresh()->deleted_at);
    }

    public function test_lessons_counter_ignores_sessions_without_participants(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'start')->first());
        // Подписка оформлена раньше занятий из этого теста
        $tutor->activeSubscription()->update(['starts_at' => now()->subDay()]);

        $room = \App\Models\Room::create([
            'user_id' => $tutor->id,
            'name' => 'Тестовая комната',
            'meeting_id' => 'test-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);

        $makeSession = fn(array $attrs) => \App\Models\MeetingSession::create(array_merge([
            'user_id' => $tutor->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'started_at' => now()->subHour(),
        ], $attrs));

        // Завершено с учеником, длилось 45 минут — считается
        $makeSession(['status' => 'completed', 'participant_count' => 2, 'ended_at' => now()->subMinutes(15)]);
        // Завершено, но учитель был один — не считается
        $makeSession(['status' => 'completed', 'participant_count' => 1, 'ended_at' => now()->subMinutes(15)]);
        // Завершено с учеником, но длилось 3 минуты — не считается
        $makeSession(['status' => 'completed', 'participant_count' => 2, 'ended_at' => now()->subHour()->addMinutes(3)]);
        // Ещё идёт — не считается
        $makeSession(['status' => 'running', 'participant_count' => 0]);

        $this->assertEquals(1, SubscriptionService::lessonsUsedThisPeriod($tutor->fresh()));
    }

    public function test_header_badge_shows_current_tariff(): void
    {
        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());

        $this->actingAs($tutor)->get('/tutor/students')
            ->assertOk()
            ->assertSee('Базовый');
    }

    public function test_header_badge_prompts_to_choose_tariff_when_none(): void
    {
        $tutor = $this->makeTutor();

        $this->actingAs($tutor)->get('/tutor/students')
            ->assertOk()
            ->assertSee('Выбрать тариф');
    }

    public function test_completing_onboarding_activates_free_tariff(): void
    {
        $tutor = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => false,
        ]);

        \App\Models\LessonType::create([
            'user_id' => $tutor->id,
            'type' => \App\Models\LessonType::TYPE_INDIVIDUAL,
            'payment_type' => 'per_lesson',
            'price' => 1000,
            'duration' => 60,
        ]);

        $this->assertNull($tutor->activeSubscription());

        Livewire::actingAs($tutor)
            ->test(\App\Filament\App\Pages\Onboarding::class)
            ->call('submit');

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertNotNull($subscription);
        $this->assertEquals(0, $subscription->tariff->price);
    }

    public function test_onboarding_redirects_to_payment_of_desired_tariff(): void
    {
        $basic = Tariff::where('slug', 'basic')->first();

        $tutor = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => false,
            'desired_tariff_id' => $basic->id,
        ]);

        \App\Models\LessonType::create([
            'user_id' => $tutor->id,
            'type' => \App\Models\LessonType::TYPE_INDIVIDUAL,
            'payment_type' => 'per_lesson',
            'price' => 1000,
            'duration' => 60,
        ]);

        Livewire::actingAs($tutor)
            ->test(\App\Filament\App\Pages\Onboarding::class)
            ->call('submit')
            ->assertRedirect(ManageSubscription::getUrl(['pay' => $basic->id], panel: 'app'));

        // Бесплатный тариф всё равно назначен как база до оплаты
        $this->assertEquals(0, $tutor->fresh()->activeSubscription()->tariff->price);
    }

    public function test_onboarding_keeps_existing_subscription(): void
    {
        $tutor = User::factory()->create([
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => false,
        ]);

        \App\Models\LessonType::create([
            'user_id' => $tutor->id,
            'type' => \App\Models\LessonType::TYPE_INDIVIDUAL,
            'payment_type' => 'per_lesson',
            'price' => 1000,
            'duration' => 60,
        ]);

        // Тариф уже выбран при регистрации
        $basic = Tariff::where('slug', 'basic')->first();
        SubscriptionService::activate($tutor, $basic);

        Livewire::actingAs($tutor)
            ->test(\App\Filament\App\Pages\Onboarding::class)
            ->call('submit');

        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_admin_can_assign_unlimited_max_tariff(): void
    {
        $tutor = $this->makeTutor();
        $admin = $this->makeAdmin();
        $master = Tariff::where('slug', 'master')->first();

        $this->actingAs($admin);
        \App\Filament\Resources\UserResource::assignSubscription($tutor, [
            'tariff_id' => $master->id,
            'unlimited' => true,
            'days' => null,
            'comment' => null,
        ]);

        $subscription = $tutor->fresh()->activeSubscription();
        $this->assertEquals($master->id, $subscription->tariff_id);
        $this->assertNull($subscription->ends_at);
        $this->assertStringContainsString('администратором', $subscription->comment);
    }

    public function test_check_command_notifies_about_expiring_subscription_once(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());
        $tutor->activeSubscription()->update(['ends_at' => now()->addDays(2)]);

        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->artisan('subscriptions:check')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentToTimes(
            $tutor,
            \App\Notifications\SubscriptionExpiringSoon::class,
            1
        );
        $this->assertTrue($tutor->activeSubscription()->isActive());
    }

    public function test_check_command_expires_subscription_and_notifies(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $tutor = $this->makeTutor();
        SubscriptionService::activate($tutor, Tariff::where('slug', 'basic')->first());
        $subscription = $tutor->activeSubscription();
        $subscription->update(['ends_at' => now()->subHour()]);

        $this->artisan('subscriptions:check')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo($tutor, \App\Notifications\SubscriptionExpired::class);
        $this->assertEquals(Subscription::STATUS_EXPIRED, $subscription->fresh()->status);
        $this->assertNull($tutor->fresh()->activeSubscription());
    }

    public function test_paid_payment_sends_notification(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
        ]);

        SubscriptionService::applyPaidPayment($payment);

        \Illuminate\Support\Facades\Notification::assertSentTo($tutor, \App\Notifications\SubscriptionPaid::class);
        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_payment_return_confirms_status_via_yookassa_api(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response(['id' => 'yk-test-1', 'status' => 'succeeded']),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-test-1',
        ]);

        $this->actingAs($tutor)
            ->get(route('subscription.payment.return', $payment))
            ->assertRedirect(route('filament.app.pages.subscription'));

        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_yookassa_webhook_activates_subscription(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.yookassa.ru/*' => \Illuminate\Support\Facades\Http::response(['id' => 'yk-test-2', 'status' => 'succeeded']),
        ]);

        $tutor = $this->makeTutor();
        $basic = Tariff::where('slug', 'basic')->first();

        $payment = \App\Models\SubscriptionPayment::create([
            'user_id' => $tutor->id,
            'tariff_id' => $basic->id,
            'amount' => $basic->price,
            'status' => \App\Models\SubscriptionPayment::STATUS_PENDING,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'yk-test-2',
        ]);

        $this->postJson('/payments/yookassa/callback', [
            'event' => 'payment.succeeded',
            'object' => ['id' => 'yk-test-2', 'status' => 'succeeded'],
        ])->assertOk();

        $this->assertEquals(\App\Models\SubscriptionPayment::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
    }

    public function test_switching_tariff_cancels_previous_subscription(): void
    {
        $tutor = $this->makeTutor();
        $free = Tariff::where('slug', 'start')->first();
        $basic = Tariff::where('slug', 'basic')->first();

        SubscriptionService::activate($tutor, $free);
        $first = $tutor->activeSubscription();

        SubscriptionService::activate($tutor, $basic);

        $this->assertEquals(Subscription::STATUS_CANCELLED, $first->fresh()->status);
        $this->assertEquals($basic->id, $tutor->fresh()->activeSubscription()->tariff_id);
        $this->assertNotNull($tutor->activeSubscription()->ends_at);
    }
}
