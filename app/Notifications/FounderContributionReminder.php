<?php

namespace App\Notifications;

use App\Models\FounderContribution;
use App\Models\FounderExpense;
use App\Services\FounderService;
use App\Support\HumanDate;
use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Письмо основателю: пора скинуться на расходы платформы (раздел админки «Основатели»).
 * contributions — невнесённые строки основателя за месяц: ежемесячный взнос и доли в разовых расходах.
 * kind: soon — за несколько дней до дня сбора, today — в день сбора, overdue — срок прошёл, а взнос не отмечен.
 * Только почта: основатель не обязательно пользователь кабинета.
 */
class FounderContributionReminder extends Notification implements ShouldQueue
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
        $share = FounderService::percent((float) $notifiable->share);

        $subject = match ($this->kind) {
            'soon' => 'Скоро сбор на расходы: ' . $amount,
            'today' => 'Сегодня сбор на расходы: ' . $amount,
            default => 'Взнос на расходы ещё не внесён: ' . $amount,
        };
        $lead = match ($this->kind) {
            'soon' => "Напоминаем: {$due} скидываемся на расходы платформы за {$month}.",
            'today' => "Сегодня, {$due}, скидываемся на расходы платформы за {$month}.",
            default => "Срок сбора за {$month} прошёл {$due}, а ваш взнос ещё не отмечен.",
        };

        $mail = (new MailMessage)
            ->subject($subject . ' — ' . Seo::SITE_NAME)
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line($lead)
            ->line('С вас — **' . $amount . '**, ваша доля ' . $share . ':');

        foreach ($this->contributions as $c) {
            $mail->line('— ' . ($c->isOneOff()
                ? 'разовый расход «' . $c->title . '»: ' . FounderService::money((float) $c->amount)
                : 'ежемесячный взнос: ' . FounderService::money((float) $c->amount) . ' (от ' . FounderService::money($this->total) . ' в месяц)'));
        }

        if ($this->expenses->isNotEmpty() && $this->contributions->contains(fn ($c) => ! $c->isOneOff())) {
            $mail->line('Из чего складываются расходы в месяц:');
            foreach ($this->expenses as $e) {
                $mail->line('— ' . $e->name . ': ' . FounderService::money((float) $e->amount)
                    . ($e->period === FounderExpense::PERIOD_YEAR ? ' в год (' . FounderService::money($e->monthly()) . ' в месяц)' : ' в месяц'));
            }
        }

        return $mail
            ->action('Открыть расходы', route('cabinet.admin.founders'))
            ->salutation('Команда ' . Seo::SITE_NAME);
    }
}
