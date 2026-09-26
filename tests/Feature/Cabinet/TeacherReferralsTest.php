<?php

namespace Tests\Feature\Cabinet;

use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Партнёрская программа в новом кабинете (/cabinet/teacher/referrals). */
class TeacherReferralsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        Notification::fake();
    }

    private function tutor(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role' => User::ROLE_TUTOR,
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
            'phone' => '+7 900 ' . random_int(1000000, 9999999),
        ]);
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.referrals'))->assertRedirect();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true]);
        $this->actingAs($student)->get(route('cabinet.teacher.referrals'))->assertForbidden();
    }

    public function test_disabled_program_is_closed(): void
    {
        Setting::updateOrCreate(['key' => 'referral_enabled'], ['value' => '0']);

        $this->actingAs($this->tutor())->get(route('cabinet.teacher.referrals'))->assertForbidden();
    }

    public function test_link_terms_and_invited_statuses(): void
    {
        Setting::updateOrCreate(['key' => 'referral_monthly_limit'], ['value' => '3']);
        $referrer = $this->tutor();

        // Оплатил — бонус начислен
        $paid = $this->tutor(['referred_by_id' => $referrer->id, 'name' => 'Коллега Оплативший']);
        $payment = SubscriptionPayment::create(['user_id' => $paid->id, 'tariff_id' => Tariff::where('slug', 'basic')->value('id'),
            'amount' => 1490, 'period_days' => 30, 'status' => SubscriptionPayment::STATUS_PENDING, 'gateway' => 'yookassa']);
        SubscriptionService::applyPaidPayment($payment);

        // Зарегистрировался, ждём оплату; заявка на рассмотрении
        $this->tutor(['referred_by_id' => $referrer->id, 'name' => 'Коллега Ждущий']);
        TeacherApplication::create(['first_name' => 'Олег', 'last_name' => 'Заявкин', 'email' => 'o@example.com', 'phone' => '+79000000000',
            'status' => 'pending', 'referred_by_id' => $referrer->id]);

        $this->actingAs($referrer)->get(route('cabinet.teacher.referrals'))
            ->assertOk()
            ->assertSee('Пригласить коллегу')
            ->assertSee('/r/' . $referrer->fresh()->referral_code)
            ->assertSee('Скопировать ссылку')
            ->assertSee('Пригласили 3 · оплатили 1 · получено +10 занятий')
            ->assertSee('+10 занятий вам')
            ->assertSee('+5 занятий коллеге')
            ->assertSee('До 3 коллег в месяц')
            ->assertSee('Коллега Оплативший')
            ->assertSee('Оплатил тариф')
            ->assertSee('Коллега Ждущий')
            ->assertSee('ждём оплату')
            ->assertSee('Олег Заявкин')
            ->assertSee('Заявка на рассмотрении');
    }

    public function test_empty_list(): void
    {
        $this->actingAs($this->tutor())->get(route('cabinet.teacher.referrals'))
            ->assertOk()
            ->assertSee('Пока никого');
    }
}
