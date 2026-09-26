<?php

namespace Tests\Feature\Cabinet;

use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Тариф учителя на виду: карточка с остатком занятий и лимитами на «Сегодня». */
class TeacherTariffVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function teacher(): User
    {
        return User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function tariff(array $attrs): Tariff
    {
        return Tariff::create($attrs + ['slug' => 's' . uniqid(), 'price' => 1490, 'period_days' => 30, 'max_participants' => 6, 'max_duration_minutes' => 90, 'recording_retention_days' => 30, 'is_active' => true]);
    }

    public function test_tariff_and_limits_are_visible(): void
    {
        $teacher = $this->teacher();
        SubscriptionService::activate($teacher, $this->tariff(['name' => 'Базовый', 'lessons_per_month' => 40]), days: 30);

        // В сайдбаре тарифа нет — только ссылка «Профиль и тариф»
        $this->actingAs($teacher)->get(route('cabinet.teacher.students'))
            ->assertOk()
            ->assertDontSee('Тариф «Базовый»')
            ->assertSee('Профиль и тариф');

        // «Сегодня» — карточка с остатком и лимитами
        $this->actingAs($teacher)->get(route('cabinet.teacher.today'))
            ->assertSee('40 из 40')->assertSee('занятий осталось')
            ->assertSee('До 6 участников в занятии · занятие до 90 минут · записи хранятся 30 дней');
    }

    public function test_low_lessons_and_expiring_are_highlighted(): void
    {
        $teacher = $this->teacher();
        SubscriptionService::activate($teacher, $this->tariff(['name' => 'Мини', 'lessons_per_month' => 2]), days: 30);
        $this->assertSame('Осталось 2 занятия', SubscriptionService::teacherSummary($teacher->fresh())['warning']);

        $other = $this->teacher();
        SubscriptionService::activate($other, $this->tariff(['name' => 'Профи', 'lessons_per_month' => null]), days: 3);
        $this->assertStringStartsWith('Тариф закончится через', SubscriptionService::teacherSummary($other->fresh())['warning']);
        $this->actingAs($other)->get(route('cabinet.teacher.today'))->assertSee('Занятий без ограничений')->assertSee('Тариф закончится через');
    }

    public function test_no_tariff(): void
    {
        $this->actingAs($this->teacher())->get(route('cabinet.teacher.today'))
            ->assertSee('Тариф не выбран')
            ->assertSee('Выберите тариф, чтобы проводить занятия');
    }
}
