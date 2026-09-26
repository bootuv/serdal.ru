<?php

namespace App\Console\Commands;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Notifications\HomeworkDeadlineSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Напоминание ученикам: срок сдачи задания в ближайшие сутки, а работа не сдана или вернулась на доработку.
 * Не напоминаем, если задание выдали меньше чем за сутки до срока — срок и так есть в уведомлении «Новое задание».
 * Каждому ученику — один раз на срок (при переносе срока напомним снова). Запуск — каждый час.
 */
class RemindHomeworkDeadlines extends Command
{
    protected $signature = 'homework:remind-deadlines';

    protected $description = 'Напомнить ученикам о сроке сдачи задания за сутки';

    public function handle(): int
    {
        $sent = 0;

        Homework::where('is_visible', true)
            ->whereBetween('deadline', [now(), now()->addDay()])
            ->where('created_at', '<', now()->subDay())
            ->with(['students', 'submissions'])
            ->each(function (Homework $homework) use (&$sent) {
                foreach ($homework->students as $student) {
                    $submission = $homework->submissions->firstWhere('student_id', $student->id);
                    $revision = $submission?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED;
                    if ($submission?->submitted_at && ! $revision) {
                        continue;
                    }

                    if (! Cache::add('homework-deadline:' . $homework->id . ':' . $student->id . ':' . $homework->deadline->timestamp, true, now()->addDays(3))) {
                        continue;
                    }

                    try {
                        $student->notify(new HomeworkDeadlineSoon($homework, $revision));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('Напоминание о сроке задания не отправлено', ['homework' => $homework->id, 'student' => $student->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Напоминаний отправлено: {$sent}");

        return self::SUCCESS;
    }
}
