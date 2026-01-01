<?php

namespace App\Observers;

use App\Jobs\ProcessHomeworkFiles;
use App\Models\Homework;

class HomeworkObserver
{
    /**
     * Handle the Homework "created" event.
     */
    public function created(Homework $homework): void
    {
        $this->dispatchProcessingJob($homework);
    }

    /**
     * Handle the Homework "updated" event.
     */
    public function updated(Homework $homework): void
    {
        // Only process if attachments were changed
        if ($homework->wasChanged('attachments')) {
            $this->dispatchProcessingJob($homework);
        }
    }

    /**
     * Dispatch the image processing job.
     */
    private function dispatchProcessingJob(Homework $homework): void
    {
        if (!empty($homework->attachments)) {
            ProcessHomeworkFiles::dispatch($homework)
                ->delay(now()->addSeconds(5));
        }
    }
}
