<?php

namespace App\Notifications;

use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Services\FounderService;
use App\Support\HumanDate;
use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Письмо основателю: пора скинуться на расходы платформы (раздел админки «Основатели»).
 * contributions — невнесённые строки основателя за месяц: ежемесячный взнос и доли в разовых расходах.
 * kind: soon — за несколько дней до дня сбора, today — в день сбора, overdue — срок прошёл, а взнос не отмечен.
 * Только почта: основатель не обязательно пользователь кабинета. Кнопка на личную страницу — если привязан профиль.
 */
class FounderContributionReminder extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  Collection<int, FounderContribution>  $contributions
     * @param  Collection<int, FounderExpense>  $expenses  ежемесячные и годовые расходы — из чего складывается ежемесячный взнос
     */
    public function __construct(
        public Collection $contributions,
        public string $kind,
        public Carbon $dueDate,
        public float $total,
        public Collection $expenses,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function amount(): float
    {
        return (float) $this->contributions->sum('amount');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = FounderService::money($this->amount());
        $month = HumanDate::month($this->contributions->first()->period);
        $due = HumanDate::date($this->dueDate);

        [$title, $subject, $lead] = match ($this->kind) {
            'soon' => ['Скоро сбор на расходы', 'Скоро сбор на расходы: ' . $amount, "{$due} скидываемся на расходы платформы за {$month}."],
            'today' => ['Сегодня сбор на расходы', 'Сегодня сбор на расходы: ' . $amount, "сегодня, {$due}, скидываемся на расходы платформы за {$month}."],
            default => ['Взнос ещё не внесён', 'Взнос на расходы ещё не внесён: ' . $amount, "срок сбора за {$month} прошёл {$due}, а ваш взнос ещё не отмечен."],
        };

        $hasMonthly = $this->contributions->contains(fn (FounderContribution $c) => ! $c->isOneOff());

        return (new MailMessage)
            ->subject($subject . ' — ' . Seo::SITE_NAME)
            ->markdown('emails.founder-reminder', [
                'title' => $title,
                'name' => $notifiable->name,
                'lead' => $lead,
                'month' => $month,
                'amount' => $amount,
                // Один ежемесячный взнос — разбивки ниже нет, поэтому «от скольки» пишем прямо здесь
                'note' => 'Доля ' . FounderService::percent((float) $notifiable->share)
                    . ($hasMonthly && $this->contributions->count() === 1 ? ' от ' . FounderService::money($this->total) . ' в месяц' : '')
                    . ' · ' . ($this->kind === 'overdue' ? 'срок был ' : 'до ') . $due,
                'lines' => $this->contributions->map(fn (FounderContribution $c) => [
                    'label' => $c->isOneOff() ? 'Разовый расход «' . $c->title . '»' : 'Ежемесячный взнос',
                    'sub' => $c->isOneOff() ? null : 'от ' . FounderService::money($this->total) . ' в месяц',
                    'value' => FounderService::money((float) $c->amount),
                ])->all(),
                'expenses' => $hasMonthly ? $this->expenses->map(fn (FounderExpense $e) => [
                    'label' => $e->name,
                    'sub' => $e->period === FounderExpense::PERIOD_YEAR ? FounderService::money((float) $e->amount) . ' в год' : null,
                    'value' => FounderService::money($e->monthly()),
                ])->all() : [],
                'payment' => FounderService::paymentRows(),
                'url' => $notifiable->pageUrl(),
            ]);
    }
}
