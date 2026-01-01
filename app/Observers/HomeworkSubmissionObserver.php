<?php

namespace App\Observers;

use App\Jobs\ProcessHomeworkAttachments;
use App\Models\HomeworkSubmission;

class HomeworkSubmissionObserver
{
    /**
     * Handle the HomeworkSubmission "created" event.
     */
    public function created(HomeworkSubmission $submission): void
    {
        $this->dispatchProcessingJob($submission);
    }

    /**
     * Handle the HomeworkSubmission "updated" event.
     */
    public function updated(HomeworkSubmission $submission): void
    {
        // Only process if attachments were changed
        if ($submission->wasChanged('attachments')) {
            $this->dispatchProcessingJob($submission);
        }
    }

    /**
     * Dispatch the image processing job.
     */
    private function dispatchProcessingJob(HomeworkSubmission $submission): void
    {
        if (!empty($submission->attachments)) {
            ProcessHomeworkAttachments::dispatch($submission, 'attachments')
                ->delay(now()->addSeconds(5)); // Small delay to ensure file is fully uploaded
        }
    }
}
