<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Onboarding;
use App\Models\Direct;
use App\Models\LessonType;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\Tariff;
use App\Models\User;
use App\Notifications\TeacherCompletedOnboarding;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Первые шаги учителя в новом кабинете (/cabinet/teacher/onboarding). */
class TeacherOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        Storage::fake('s3');
        Notification::fake();
        SubscriptionService::flushCanStartCache();
    }

    private function tutor(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_TUTOR,
            'first_name' => 'Мария',
            'last_name' => 'Соколова',
            'username' => 'tutor' . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => false,
        ], $attrs));
    }

    private function cabinetUrl(): string
    {
        return Route::has('cabinet.teacher.today') ? route('cabinet.teacher.today') : url('/tutor');
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.teacher.onboarding'))->assertRedirect();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true]);
        $this->actingAs($student)->get(route('cabinet.teacher.onboarding'))->assertRedirect(route('cabinet.student.home'));
    }

    public function test_completed_profile_goes_to_cabinet(): void
    {
        $this->actingAs($this->tutor(['is_profile_completed' => true]))
            ->get(route('cabinet.teacher.onboarding'))
            ->assertRedirect($this->cabinetUrl());
    }

    public function test_wizard_saves_everything_and_shows_invite(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid(), 'is_active' => true]);
        $tutor = $this->tutor();
        $ege = Direct::create(['name' => 'ЕГЭ']);

        $this->actingAs($tutor)->get(route('cabinet.teacher.onboarding'))
            ->assertOk()
            ->assertSee('Добро пожаловать, Мария!')
            ->assertSee('Пропустить')
            ->assertSee('ЕГЭ')
            ->assertSee('5–9 класс');

        $c = Livewire::actingAs($tutor)->test(Onboarding::class)
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->call('toggleDirect', $ege->id)
            ->call('toggleGradeGroup', 'senior')
            ->call('toggleGradeGroup', 'adults')
            ->set('telegram', '@maria')
            ->call('next')
            ->assertSet('step', 2)
            // Цена обязательна
            ->call('next')
            ->assertHasErrors(['lessonTypes.0.price'])
            ->set('lessonTypes.0.price', '1500')
            ->call('addLessonType')
            ->assertSet('lessonTypes.1.type', LessonType::TYPE_GROUP)
            ->assertSet('lessonTypes.1.payment_type', LessonType::PAYMENT_MONTHLY)
            ->set('lessonTypes.1.price', '6000')
            ->set('lessonTypes.1.duration', '90')
            ->call('next')
            ->assertHasErrors(['lessonTypes.1.count_per_week'])
            ->set('lessonTypes.1.count_per_week', '2')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Завершить настройку')
            ->call('finish')
            ->assertSet('done', true)
            ->assertSee('Всё готово, Мария!')
            ->assertSee('Пригласите первого ученика')
            ->assertSee('register/invite')
            ->assertSee('Перейти в кабинет');

        $tutor->refresh();
        $this->assertTrue((bool) $tutor->is_profile_completed);
        $this->assertSame([$ege->id], $tutor->directs()->pluck('directs.id')->all());
        $this->assertSame(['10', '11', 'adults'], $tutor->grade);
        $this->assertSame('maria', $tutor->telegram);
        $this->assertNotNull($tutor->avatar);
        $this->assertSame(2, $tutor->lessonTypes()->count());
        $this->assertSame(2, (int) $tutor->lessonTypes()->where('type', LessonType::TYPE_GROUP)->value('count_per_week'));
        $this->assertNull($tutor->lessonTypes()->where('type', LessonType::TYPE_INDIVIDUAL)->value('count_per_week'));
        // Бесплатный «Старт» — база, админы узнали
        $this->assertSame(0, (int) $tutor->activeSubscription()->tariff->price);
        Notification::assertSentTo($admin, TeacherCompletedOnboarding::class);
    }

    public function test_paid_tariff_redirects_to_payment(): void
    {
        Setting::updateOrCreate(['key' => 'yookassa_shop_id'], ['value' => '123']);
        Setting::updateOrCreate(['key' => 'yookassa_secret_key'], ['value' => 'test_key']);
        Http::fake(['api.yookassa.ru/*' => Http::response([
            'id' => 'yk-onb', 'status' => 'pending', 'confirmation' => ['confirmation_url' => 'https://pay.test/onb'],
        ])]);

        $basic = Tariff::where('slug', 'basic')->first();
        $tutor = $this->tutor(['desired_tariff_id' => $basic->id]);

        Livewire::actingAs($tutor)->test(Onboarding::class)
            ->assertSet('tariffId', $basic->id)
            ->set('lessonTypes.0.price', '1000')
            ->set('step', 3)
            ->assertSee('Завершить и оплатить')
            ->assertSee('Способ оплаты')
            ->call('finish')
            ->assertRedirect('https://pay.test/onb');

        $payment = SubscriptionPayment::where('user_id', $tutor->id)->first();
        $this->assertSame($basic->id, $payment->tariff_id);
        $this->assertSame(0, (int) $tutor->fresh()->activeSubscription()->tariff->price);
    }

    public function test_paid_tariff_without_online_payment_finishes_on_free(): void
    {
        $basic = Tariff::where('slug', 'basic')->first();
        $tutor = $this->tutor(['desired_tariff_id' => $basic->id]);

        Livewire::actingAs($tutor)->test(Onboarding::class)
            ->set('lessonTypes.0.price', '1000')
            ->call('finish')
            ->assertSet('done', true)
            ->assertSee('Онлайн-оплата подключается');

        $this->assertSame(0, (int) $tutor->fresh()->activeSubscription()->tariff->price);
    }

    public function test_repeated_completion_does_not_notify_admins_again(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'a' . uniqid(), 'is_active' => true]);
        $tutor = $this->tutor();

        Livewire::actingAs($tutor)->test(Onboarding::class)
            ->set('lessonTypes.0.price', '1500')
            ->call('finish')
            ->assertSet('done', true)
            ->call('restart')
            ->set('lessonTypes.0.price', '1700')
            ->call('finish')
            ->assertSet('done', true);

        Notification::assertSentToTimes($admin, TeacherCompletedOnboarding::class, 1);
        $this->assertSame(1700, (int) $tutor->lessonTypes()->value('price'));
    }

    public function test_repeated_completion_with_paid_tariff_reuses_open_payment(): void
    {
        Setting::updateOrCreate(['key' => 'yookassa_shop_id'], ['value' => '123']);
        Setting::updateOrCreate(['key' => 'yookassa_secret_key'], ['value' => 'test_key']);
        Http::fake(['api.yookassa.ru/*' => Http::response([
            'id' => 'yk-onb', 'status' => 'pending', 'confirmation' => ['confirmation_url' => 'https://pay.test/onb'],
        ])]);

        $basic = Tariff::where('slug', 'basic')->first();
        $tutor = $this->tutor(['desired_tariff_id' => $basic->id]);

        $c = Livewire::actingAs($tutor)->test(Onboarding::class)
            ->set('lessonTypes.0.price', '1000')
            ->call('finish')
            ->assertRedirect('https://pay.test/onb');

        // Вернулся со страницы оплаты, не заплатив, и прошёл настройку ещё раз — новый платёж не создаётся
        $c->call('finish')->assertRedirect('https://pay.test/onb');

        $this->assertSame(1, SubscriptionPayment::where('user_id', $tutor->id)->count());
    }

    public function test_whatsapp_is_checked_like_in_profile(): void
    {
        $tutor = $this->tutor();

        Livewire::actingAs($tutor)->test(Onboarding::class)
            ->set('whatsup', 'позвоните мне')
            ->call('next')
            ->assertHasErrors(['whatsup'])
            ->assertSet('step', 1)
            ->set('whatsup', '+7 916 123-45-67')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }
}
