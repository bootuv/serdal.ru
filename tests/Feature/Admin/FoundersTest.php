<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Founders;
use App\Models\Founder;
use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Models\User;
use App\Notifications\FounderContributionReminder;
use App\Services\FounderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Основатели (/cabinet/admin/founders): доли, расходы, взносы и напоминания. */
class FoundersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function seedData(): array
    {
        FounderExpense::create(['name' => 'Сервер', 'amount' => 9000, 'period' => 'month']);
        FounderExpense::create(['name' => 'Домен', 'amount' => 1200, 'period' => 'year']);
        FounderExpense::create(['name' => 'Старый сервис', 'amount' => 5000, 'period' => 'month', 'is_active' => false]);

        return [
            Founder::create(['name' => 'Иван', 'email' => 'ivan@example.com', 'share' => 60]),
            Founder::create(['name' => 'Пётр', 'email' => 'petr@example.com', 'share' => 40]),
        ];
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/founders')->assertRedirect(route('login'));
        $tutor = $this->user(User::ROLE_TUTOR);
        $this->actingAs($tutor)->get('/cabinet/admin/founders')->assertRedirect(EnsureCabinetRole::homeFor($tutor));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get('/cabinet/admin/founders')->assertOk()->assertSee('Основатели')->assertSee('Пока нет основателей');
    }

    public function test_contributions_follow_expenses_and_shares(): void
    {
        [$ivan, $petr] = $this->seedData();
        $service = app(FounderService::class);

        // 9000 + 1200/12 = 9100 в месяц, выключенный расход не считается
        $this->assertSame(9100.0, $service->monthlyTotal());

        $period = now()->startOfMonth();
        $rows = $service->sync($period)->keyBy('founder_id');
        $this->assertSame(5460.0, (float) $rows[$ivan->id]->amount);
        $this->assertSame(3640.0, (float) $rows[$petr->id]->amount);

        // Внесённый взнос не пересчитывается, невнесённый — да
        $service->setPaid($rows[$ivan->id], true);
        FounderExpense::create(['name' => 'Почта', 'amount' => 900, 'period' => 'month']);
        $rows = $service->sync($period)->keyBy('founder_id');
        $this->assertSame(5460.0, (float) $rows[$ivan->id]->amount);
        $this->assertSame(4000.0, (float) $rows[$petr->id]->amount);
    }

    public function test_screen_crud_and_paid_toggle(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));

        Livewire::test(Founders::class)
            ->set('tab', 'shares')
            ->call('editFounder')
            ->set('founderName', 'Иван')->set('founderEmail', 'ivan@example.com')->set('founderShare', '33,5')
            ->call('saveFounder')->assertHasNoErrors()
            ->call('editFounder')
            ->set('founderName', 'Пётр')->set('founderShare', '120')
            ->call('saveFounder')->assertHasErrors('founderShare')
            ->set('founderShare', '66,5')->call('saveFounder')->assertHasNoErrors()
            ->assertSee('33,5 %')->assertSee('100 %')
            ->set('tab', 'expenses')
            ->call('editExpense')
            ->set('expenseName', 'Сервер')->set('expenseAmount', '12 000')->set('expensePeriod', 'year')
            ->call('saveExpense')->assertHasNoErrors()
            ->assertSee('1 000 ₽');

        $this->assertSame(1, FounderExpense::count());
        $this->assertSame(33.5, (float) Founder::where('name', 'Иван')->value('share'));

        $component = Livewire::test(Founders::class)->assertSee('Взнос за')->assertSee('335 ₽')->assertSee('665 ₽');
        $c = FounderContribution::whereHas('founder', fn ($q) => $q->where('name', 'Иван'))->firstOrFail();
        $component->call('togglePaid', $c->id);
        $this->assertNotNull($c->fresh()->paid_at);

        // Удаление основателя
        $component->set('tab', 'shares')->call('editFounder', $c->founder_id)->call('askDelete', 'founder')->call('confirmDelete');
        $this->assertNull(Founder::find($c->founder_id));
    }

    public function test_settings(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));
        Livewire::test(Founders::class)
            ->call('openSettings')->set('day', '40')->call('saveSettings')->assertHasErrors('day')
            ->set('day', '10')->set('remindDays', '2')->set('reminders', false)->call('saveSettings')->assertHasNoErrors();

        $this->assertSame(['day' => 10, 'remindDays' => 2, 'reminders' => false], FounderService::settings());
    }

    public function test_reminders_go_to_unpaid_founders_on_schedule(): void
    {
        Notification::fake();
        [$ivan, $petr] = $this->seedData();
        FounderService::saveSettings(31, 3, true);
        $service = app(FounderService::class);

        // Октябрь 2026: сбор 31-го, напоминание за 3 дня — 28-го
        Carbon::setTestNow('2026-10-27 10:00');
        $this->assertSame(0, $service->sendReminders());

        Carbon::setTestNow('2026-10-28 10:00');
        $c = FounderContribution::firstOrCreate(['founder_id' => $ivan->id, 'period' => '2026-10-01']);
        $service->setPaid($c, true);
        $this->artisan('founders:remind')->assertSuccessful();
        Notification::assertSentTo($petr, FounderContributionReminder::class, fn ($n) => $n->kind === 'soon' && (float) $n->contribution->amount === 3640.0);
        Notification::assertNotSentTo($ivan, FounderContributionReminder::class);

        // Повторно в тот же день не шлём
        $this->assertSame(0, $service->sendReminders());

        // Ноябрь короче: сбор 30-го, в день сбора
        Carbon::setTestNow('2026-11-30 10:00');
        $this->assertSame(2, $service->sendReminders());

        // Через 3 дня после сбора — тем, кто не внёс (переход через месяц)
        Carbon::setTestNow('2026-12-03 10:00');
        $service->setPaid(FounderContribution::where('founder_id', $ivan->id)->whereDate('period', '2026-11-01')->first(), true);
        $this->assertSame(1, $service->sendReminders());

        // Выключено — не шлём
        FounderService::saveSettings(31, 3, false);
        Carbon::setTestNow('2026-12-28 10:00');
        $this->assertSame(0, $service->sendReminders());
    }

    public function test_reminder_mail_content(): void
    {
        [$ivan] = $this->seedData();
        $service = app(FounderService::class);
        $c = $service->sync(Carbon::parse('2026-10-01'))->firstWhere('founder_id', $ivan->id);
        $mail = (new FounderContributionReminder($c, 'today', Carbon::parse('2026-10-05'), 9100, FounderExpense::where('is_active', true)->get()))->toMail($ivan);

        $this->assertStringContainsString('5 460 ₽', $mail->subject);
        $text = implode("\n", $mail->introLines);
        $this->assertStringContainsString('60 % от 9 100 ₽', $text);
        $this->assertStringContainsString('Домен: 1 200 ₽ в год (100 ₽ в месяц)', $text);
    }

    public function test_history_debts_and_mark_all(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin);
        [$ivan, $petr] = $this->seedData();
        FounderService::saveSettings(5, 3, true);
        $service = app(FounderService::class);

        // Август и сентябрь: Иван внёс оба раза, Пётр — нет
        Carbon::setTestNow('2026-08-02 10:00');
        $service->sync(now());
        $service->setPaid(FounderContribution::where('founder_id', $ivan->id)->whereDate('period', '2026-08-01')->first(), true, $admin);
        Carbon::setTestNow('2026-09-02 10:00');
        $service->sync(now());
        $service->setPaid(FounderContribution::where('founder_id', $ivan->id)->whereDate('period', '2026-09-01')->first(), true, $admin);

        // Октябрь, сбор ещё не наступил
        Carbon::setTestNow('2026-10-03 10:00');
        $this->assertSame(2, $service->debts()->count());
        $totals = $service->totals()->keyBy(fn ($t) => $t['founder']->id);
        $this->assertSame(10920.0, $totals[$ivan->id]['paid']);
        $this->assertSame(7280.0, $totals[$petr->id]['debt']);
        $this->assertSame(2, $totals[$petr->id]['debtMonths']);

        $component = Livewire::test(Founders::class)
            ->assertSee('Долги')->assertSee('7 280 ₽')
            ->set('tab', 'history')
            ->assertSee('Сентябрь')->assertSee('Август')->assertSee('Октябрь')
            ->assertSee('отметка — ' . $admin->name)
            ->assertSee('Долг 7 280 ₽ · 2 месяца')
            ->set('historyFilter', 'debts')
            ->assertSee('Сентябрь')->assertDontSee('Октябрь');

        $component->call('markAllPaid', '2026-09');
        $this->assertSame(0, FounderContribution::whereDate('period', '2026-09-01')->whereNull('paid_at')->count());
        $this->assertSame($admin->id, FounderContribution::where('founder_id', $petr->id)->whereDate('period', '2026-09-01')->value('paid_by_id'));
        $this->assertSame(1, $service->debts()->count());

        // Снять отметку
        $c = FounderContribution::where('founder_id', $petr->id)->whereDate('period', '2026-09-01')->first();
        $component->call('togglePaid', $c->id);
        $this->assertNull($c->fresh()->paid_at);
        $this->assertNull($c->fresh()->paid_by_id);
    }

    public function test_command_records_current_month_without_reminders(): void
    {
        $this->seedData();
        FounderService::saveSettings(5, 3, false);
        Carbon::setTestNow('2026-10-20 10:00');
        $this->artisan('founders:remind')->assertSuccessful();
        $this->assertSame(2, FounderContribution::whereDate('period', '2026-11-01')->count());
    }
}
