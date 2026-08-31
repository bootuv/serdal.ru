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
            ->assertSee('Возврат денежных средств');
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
