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
 * kind: soon — за несколько дней до дня сбора, today — в день сбора, overdue — срок прошёл, а взнос не отмечен.
 * Только почта: основатель не обязательно пользователь кабинета.
 */
class FounderContributionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FounderContribution $contribution,
        public string $kind,
        public Carbon $dueDate,
        public float $total,
        public Collection $expenses,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contribution;
        $amount = FounderService::money((float) $c->amount);
        $month = HumanDate::month($c->period);
        $due = HumanDate::date($this->dueDate);

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
            ->line('Ваш взнос — **' . $amount . '** (' . FounderService::percent((float) $notifiable->share) . ' от ' . FounderService::money($this->total) . ' в месяц).');

        if ($this->expenses->isNotEmpty()) {
            $mail->line('Из чего складываются расходы:');
            foreach ($this->expenses as $e) {
                /** @var FounderExpense $e */
                $mail->line('— ' . $e->name . ': ' . FounderService::money((float) $e->amount)
                    . ($e->period === FounderExpense::PERIOD_YEAR ? ' в год (' . FounderService::money($e->monthly()) . ' в месяц)' : ' в месяц'));
            }
        }

        return $mail
            ->action('Открыть расходы', route('cabinet.admin.founders'))
            ->salutation('Команда ' . Seo::SITE_NAME);
    }
}
