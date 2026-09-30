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
        Notification::assertSentTo($petr, FounderContributionReminder::class, fn ($n) => $n->kind === 'soon' && $n->amount() === 3640.0);
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
        $c = $service->sync(Carbon::parse('2026-10-01'))->where('founder_id', $ivan->id)->values();
        $mail = (new FounderContributionReminder($c, 'today', Carbon::parse('2026-10-05'), 9100, FounderExpense::where('is_active', true)->get()))->toMail($ivan);

        $this->assertStringContainsString('5 460 ₽', $mail->subject);
        $html = (string) $mail->render();
        $this->assertStringContainsString('Сегодня сбор на расходы', $html);
        $this->assertStringContainsString('Доля 60 % от 9 100 ₽ в месяц · до 5 октября', $html);
        $this->assertStringContainsString('1 200 ₽ в год', $html);
        $this->assertStringContainsString('Домен', $html);
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

    public function test_one_off_expense_is_collected_separately(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin);
        Notification::fake();
        [$ivan, $petr] = $this->seedData();
        FounderService::saveSettings(5, 3, true);
        $service = app(FounderService::class);
        Carbon::setTestNow('2026-10-01 10:00');

        // Иван уже внёс ежемесячный взнос за октябрь
        $service->sync(now());
        $service->setPaid(FounderContribution::where('founder_id', $ivan->id)->whereNull('title')->first(), true, $admin);

        Livewire::test(Founders::class)
            ->set('tab', 'expenses')
            ->call('editExpense')
            ->set('expenseName', 'Лицензия')->set('expenseAmount', '10000')->set('expensePeriod', 'once')
            ->assertSee('По долям: Иван — 6 000 ₽, Пётр — 4 000 ₽')
            ->assertSet('expenseCharge', '2026-10')
            ->call('saveExpense')->assertHasNoErrors()
            ->assertSee('Разовые расходы')->assertSee('в сбор за октябрь');

        // Ежемесячные расходы не выросли, внесённый взнос Ивана не пересчитан — доля в лицензии отдельной строкой
        $this->assertSame(9100.0, $service->monthlyTotal());
        $rows = $service->sync(now());
        $this->assertCount(4, $rows);
        $ivanRows = $rows->where('founder_id', $ivan->id);
        $this->assertSame(5460.0, (float) $ivanRows->firstWhere('title', null)->amount);
        $this->assertNotNull($ivanRows->firstWhere('title', null)->paid_at);
        $this->assertSame(6000.0, (float) $ivanRows->firstWhere('title', 'Лицензия')->amount);
        $this->assertNull($ivanRows->firstWhere('title', 'Лицензия')->paid_at);

        Livewire::test(Founders::class)->assertSee('Разово: Лицензия')->assertSee('не внесли 2 из 2');

        // Напоминание: одно письмо, в нём всё невнесённое
        Carbon::setTestNow('2026-10-02 10:00');
        $this->assertSame(2, $service->sendReminders());
        Notification::assertSentTo($ivan, FounderContributionReminder::class, fn ($n) => $n->amount() === 6000.0);
        Notification::assertSentTo($petr, FounderContributionReminder::class, fn ($n) => $n->amount() === 7640.0);

        // Перенесли на ноябрь — невнесённые доли переехали
        $expense = FounderExpense::where('name', 'Лицензия')->first();
        $expense->update(['charge_period' => '2026-11-01']);
        $this->assertCount(2, $service->sync(Carbon::parse('2026-10-01')));
        $this->assertSame(2, $service->sync(Carbon::parse('2026-11-01'))->whereNotNull('title')->count());

        // Удалили: невнесённое пропало, внесённое осталось в истории
        $paid = FounderContribution::where('founder_id', $ivan->id)->where('title', 'Лицензия')->first();
        $service->setPaid($paid, true, $admin);
        Livewire::test(Founders::class)->set('tab', 'expenses')->call('editExpense', $expense->id)->call('askDelete', 'expense')->call('confirmDelete');
        $this->assertSame(1, FounderContribution::where('title', 'Лицензия')->count());
        $this->assertNotNull(FounderContribution::where('title', 'Лицензия')->first()->paid_at);
        $this->assertCount(3, $service->sync(Carbon::parse('2026-11-01')));
    }

    public function test_manual_reminder(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin);
        Notification::fake();
        [$ivan, $petr] = $this->seedData();
        $noMail = Founder::create(['name' => 'Анна', 'share' => 0.5]);
        // Авто-напоминания выключены — ручная отправка всё равно работает
        FounderService::saveSettings(5, 3, false);
        Carbon::setTestNow('2026-10-04 12:00');

        $component = Livewire::test(Founders::class)
            ->assertSee('Напомнить на почту')
            ->call('openRemind', '2026-10')
            ->assertSet('remindIds', [(string) $ivan->id, (string) $petr->id])
            ->assertSee('Напомнить о взносе за октябрь')->assertSee('почта не указана')
            ->set('remindIds', [(string) $petr->id])
            ->call('sendRemind')
            ->assertSet('remindMonth', null);

        Notification::assertSentTo($petr, FounderContributionReminder::class, fn ($n) => $n->kind === 'soon' && $n->amount() === 3640.0);
        Notification::assertNotSentTo($ivan, FounderContributionReminder::class);
        Notification::assertNotSentTo($noMail, FounderContributionReminder::class);

        // Повторно в тот же день — уходит; внёсшим — нет
        $service = app(FounderService::class);
        $service->setPaid(FounderContribution::where('founder_id', $petr->id)->first(), true, $admin);
        $this->assertSame(1, $service->remindNow(Carbon::parse('2026-10-01'), [$ivan->id, $petr->id, $noMail->id]));
        $component->call('openRemind', '2026-10')->assertSee('напоминали сегодня в 12:00');

        // После срока — письмо о просрочке
        Carbon::setTestNow('2026-10-09 12:00');
        $service->remindNow(Carbon::parse('2026-10-01'), [$ivan->id]);
        Notification::assertSentTo($ivan, FounderContributionReminder::class, fn ($n) => $n->kind === 'overdue');
    }

    public function test_founder_page_requires_linked_profile_and_claim(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $tutor = $this->user(User::ROLE_TUTOR);
        $stranger = $this->user(User::ROLE_TUTOR);
        [$ivan, $petr] = $this->seedData();
        $ivan->update(['user_id' => $tutor->id]);
        $petr->update(['user_id' => $admin->id]);
        FounderService::saveSettings(5, 3, true);
        FounderService::savePayment(['recipient' => 'Берд А.', 'number' => '+7 900 000-00-00', 'bank' => 'Т-Банк', 'note' => '']);
        $service = app(FounderService::class);
        Carbon::setTestNow('2026-10-02 10:00');

        // Без входа — на страницу входа; под чужим профилем — «Такой страницы нет»
        $this->get('/founder')->assertRedirect(route('login'));
        $this->actingAs($stranger)->get('/founder')->assertNotFound();
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/founder')->assertNotFound();

        // Учитель-основатель видит только своё
        $this->actingAs($tutor)->get('/founder')->assertOk()
            ->assertSee('Сбор на расходы')->assertSee('Иван · доля 60 %')->assertSee('5 460 ₽')
            ->assertSee('Куда переводить')->assertSee('+7 900 000-00-00')->assertSee('Т-Банк')
            ->assertSee('Сервер')->assertSee('noindex')
            ->assertDontSee('3 640 ₽')->assertDontSee('Пётр');

        // «Я перевёл» — ждёт подтверждения, админу уведомление, автонапоминания не приходят
        Livewire::test(\App\Livewire\FounderPage::class)
            ->assertSee('Я перевёл')
            ->call('claim')
            ->assertSee('Вы сообщили о переводе сегодня')->assertSee('Отменить');
        $c = FounderContribution::where('founder_id', $ivan->id)->first();
        $this->assertNotNull($c->claimed_at);
        $this->assertNull($c->paid_at);
        $this->assertNull(FounderContribution::where('founder_id', $petr->id)->first()->claimed_at);
        Notification::assertSentTo($admin, \App\Notifications\FounderClaimedPayment::class, fn ($n) => $n->founderName === 'Иван' && $n->amount === 5460.0);

        $this->assertSame(1, $service->sendReminders());
        Notification::assertSentTo($petr, FounderContributionReminder::class);
        Notification::assertNotSentTo($ivan, FounderContributionReminder::class);

        // Админ видит сообщение и подтверждает
        $this->actingAs($admin);
        Livewire::test(Founders::class)->assertSee('Сообщение о переводе — сегодня, проверьте и отметьте')->call('togglePaid', $c->id);
        $this->actingAs($tutor)->get('/founder')->assertOk()->assertSee('Внесено')->assertDontSee('Я перевёл');

        // Админ-основатель: своя страница, отмена «Я перевёл»
        $this->actingAs($admin);
        Livewire::test(\App\Livewire\FounderPage::class)->assertSee('Пётр · доля 40 %')->call('claim')->call('unclaim')->assertSee('Я перевёл');
        $this->assertNull(FounderContribution::where('founder_id', $petr->id)->first()->claimed_at);
    }

    public function test_admin_links_founder_to_profile(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 'tt' . uniqid(), 'name' => 'Ахмед Мислауров', 'email' => 'ahmed@example.com']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'st' . uniqid(), 'name' => 'Ахмед Ученик']);
        [$ivan, $petr] = $this->seedData();
        $this->actingAs($admin);

        Livewire::test(Founders::class)->set('tab', 'shares')
            ->assertSee('профиль не привязан')
            ->call('editFounder', $ivan->id)
            ->set('founderUserQuery', 'Ахмед')
            ->assertSee('Ахмед Мислауров')->assertDontSee('Ахмед Ученик')
            ->call('pickFounderUser', $tutor->id)
            ->assertSee('Отвязать')
            ->call('saveFounder')->assertHasNoErrors();
        $this->assertSame($tutor->id, $ivan->fresh()->user_id);
        $this->assertSame(route('founders.page'), $ivan->fresh()->pageUrl());

        // Один профиль — один основатель; ученика привязать нельзя
        $component = Livewire::test(Founders::class)->call('editFounder', $petr->id)->set('founderUserQuery', 'Ахмед')->assertDontSee('Ахмед Мислауров');
        $component->set('founderUserId', $tutor->id)->call('saveFounder');
        $this->assertNull($petr->fresh()->user_id);
        $component->call('editFounder', $petr->id)->set('founderUserId', $student->id)->call('saveFounder');
        $this->assertNull($petr->fresh()->user_id);

        // Отвязать
        Livewire::test(Founders::class)->call('editFounder', $ivan->id)->call('clearFounderUser')->call('saveFounder');
        $this->assertNull($ivan->fresh()->user_id);
        $this->assertNull($ivan->fresh()->pageUrl());
    }

    public function test_reminder_mail_has_payment_details_and_personal_link(): void
    {
        [$ivan] = $this->seedData();
        FounderService::savePayment(['recipient' => 'Берд А.', 'number' => '2200 0000 0000 0000', 'bank' => 'Т-Банк', 'note' => 'Serdal, октябрь']);
        $service = app(FounderService::class);
        $c = $service->sync(Carbon::parse('2026-10-01'))->where('founder_id', $ivan->id)->values();
        $html = (string) (new FounderContributionReminder($c, 'soon', Carbon::parse('2026-10-05'), 9100, collect()))->toMail($ivan)->render();

        $this->assertStringContainsString('Куда переводить', $html);
        $this->assertStringContainsString('2200 0000 0000 0000', $html);
        $this->assertStringContainsString('Serdal, октябрь', $html);
        // Без профиля — кнопки нет, всё нужное в письме; с профилем — кнопка на страницу сбора
        $this->assertStringNotContainsString('Открыть мой сбор', $html);
        $this->assertStringNotContainsString('cabinet/admin', $html);

        $ivan->update(['user_id' => $this->user(User::ROLE_TUTOR)->id]);
        $html = (string) (new FounderContributionReminder($c, 'soon', Carbon::parse('2026-10-05'), 9100, collect()))->toMail($ivan->fresh())->render();
        $this->assertStringContainsString('Открыть мой сбор', $html);
        $this->assertStringContainsString(route('founders.page'), $html);
    }

    public function test_payment_settings(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));
        Livewire::test(Founders::class)->call('openSettings')
            ->set('payNumber', ' +7 900 000-00-00 ')->set('payBank', 'Сбер')->call('saveSettings')->assertHasNoErrors();

        $this->assertSame('+7 900 000-00-00', FounderService::payment()['number']);
        $this->assertSame([['label' => 'Номер', 'value' => '+7 900 000-00-00'], ['label' => 'Банк', 'value' => 'Сбер']], FounderService::paymentRows());
    }
}
