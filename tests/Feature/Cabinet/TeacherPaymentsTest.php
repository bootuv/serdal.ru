<?php

namespace Tests\Feature\Cabinet;

use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Все платежи учителя за тариф (/cabinet/teacher/payments). */
class TeacherPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
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

    private function payment(User $tutor, array $attrs): SubscriptionPayment
    {
        return SubscriptionPayment::create(array_merge([
            'user_id' => $tutor->id,
            'tariff_id' => Tariff::where('slug', 'basic')->value('id'),
            'amount' => 1490,
            'period_days' => 30,
            'status' => SubscriptionPayment::STATUS_PAID,
            'gateway' => 'yookassa',
        ], $attrs));
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.payments'))->assertRedirect();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true]);
        $this->actingAs($student)->get(route('cabinet.teacher.payments'))->assertRedirect(route('cabinet.student.home'));
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->tutor())
            ->get(route('cabinet.teacher.payments'))
            ->assertOk()
            ->assertSee('Все платежи')
            ->assertSee('Платежей пока нет')
            ->assertSee(route('cabinet.teacher.subscription'), false);
    }

    public function test_payments_by_month_with_statuses_receipt_and_pay_link(): void
    {
        $tutor = $this->tutor();
        $other = $this->tutor();

        $paid = $this->payment($tutor, ['paid_at' => now()]);
        $old = $this->payment($tutor, ['amount' => 990]);
        $old->forceFill(['created_at' => now()->subMonthsNoOverflow(2), 'updated_at' => now()->subMonthsNoOverflow(2)])->saveQuietly();
        $pending = $this->payment($tutor, ['amount' => 2490, 'status' => SubscriptionPayment::STATUS_PENDING, 'payment_url' => 'https://pay.test/resume']);
        $this->payment($tutor, ['amount' => 1, 'status' => SubscriptionPayment::STATUS_REFUNDED, 'meta' => ['card_binding' => true]]);
        $this->payment($tutor, ['amount' => 777, 'status' => SubscriptionPayment::STATUS_FAILED]);
        $this->payment($other, ['amount' => 555]);

        $this->actingAs($tutor)
            ->get(route('cabinet.teacher.payments'))
            ->assertOk()
            ->assertSee(\Illuminate\Support\Str::ucfirst(\App\Support\HumanDate::month(now())))
            ->assertSee(\Illuminate\Support\Str::ucfirst(\App\Support\HumanDate::month($old->created_at)))
            ->assertSee(route('subscription.payment.receipt', $paid), false)
            ->assertSee(route('subscription.payment.receipt', $old), false)
            ->assertDontSee(route('subscription.payment.receipt', $pending), false)
            ->assertSee('https://pay.test/resume', false)
            ->assertSee('Оплатить')
            ->assertSee('Вернули')
            ->assertSee('Проверочный 1 ₽ возвращён')
            ->assertDontSee('777 ₽')
            ->assertDontSee('555 ₽');
    }
}
