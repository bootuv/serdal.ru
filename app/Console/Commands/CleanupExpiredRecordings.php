<?php

namespace App\Console\Commands;

use App\Models\Recording;
use App\Models\Subscription;
use Illuminate\Console\Command;

class CleanupExpiredRecordings extends Command
{
    protected $signature = 'recordings:cleanup';

    protected $description = 'Удаляет записи занятий старше срока хранения по тарифу владельца';

    public function handle(): int
    {
        $deleted = 0;

        // Только пользователи с активной подпиской и ограниченным сроком хранения.
        // Без активной подписки записи не трогаем (новые всё равно не создаются —
        // запись отключена при создании встречи), чтобы не удалять данные
        // безвозвратно из-за просроченной оплаты.
        $subscriptions = Subscription::active()
            ->with('tariff')
            ->get()
            ->filter(fn(Subscription $s) => $s->tariff->recording_retention_days !== null);

        foreach ($subscriptions as $subscription) {
            $cutoff = now()->subDays($subscription->tariff->recording_retention_days);

            $expired = Recording::whereHas('room', fn($q) => $q->where('user_id', $subscription->user_id))
                ->where(function ($q) use ($cutoff) {
                    $q->where('end_time', '<', $cutoff)
                        ->orWhere(fn($q2) => $q2->whereNull('end_time')->where('created_at', '<', $cutoff));
                })
                ->get();

            // Удаляем по одной, чтобы сработал хук модели, чистящий файл в S3
            foreach ($expired as $recording) {
                $recording->delete();
                $deleted++;
            }
        }

        $this->info("Удалено записей: {$deleted}");

        return self::SUCCESS;
    }
}
