<?php

namespace App\Console\Commands;

use App\Models\PaymentRecord;
use App\Notifications\PaymentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverduePayments extends Command
{
    protected $signature = 'payments:check-overdue';

    protected $description = 'Отправить ученикам напоминания о просроченных оплатах (один раз на начисление)';

    public function handle(): int
    {
        $records = PaymentRecord::overdue()
            ->whereNull('reminded_at')
            ->with(['student', 'teacher'])
            ->get()
            ->groupBy('student_id');

        $notified = 0;

        foreach ($records as $studentRecords) {
            $student = $studentRecords->first()->student;

            if (!$student) {
                continue;
            }

            try {
                $student->notify(new PaymentReminder($studentRecords->first()->teacher, $studentRecords->count()));
                PaymentRecord::whereIn('id', $studentRecords->pluck('id'))->update(['reminded_at' => now()]);
                $notified++;
            } catch (\Throwable $e) {
                Log::error("[Payments] Failed to notify student {$student->id}: " . $e->getMessage());
            }
        }

        $this->info("Отправлено напоминаний: {$notified}");

        return self::SUCCESS;
    }
}
