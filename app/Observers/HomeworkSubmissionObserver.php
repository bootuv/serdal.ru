<?php

namespace App\Observers;

use App\Jobs\ProcessHomeworkAttachments;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use Illuminate\Support\Facades\Storage;

class HomeworkSubmissionObserver
{
    /**
     * Handle the HomeworkSubmission "created" event.
     */
    public function created(HomeworkSubmission $submission): void
    {
        $this->dispatchProcessingJob($submission);

        // Log initial submission
        if ($submission->status === HomeworkSubmission::STATUS_SUBMITTED) {
            HomeworkActivity::log($submission->id, HomeworkActivity::TYPE_SUBMITTED, $submission->student_id);
        }
    }

    /**
     * Handle the HomeworkSubmission "updated" event.
     */
    public function updated(HomeworkSubmission $submission): void
    {
        // Only process attachments if they were changed
        if ($submission->wasChanged('attachments')) {
            $this->dispatchProcessingJob($submission);
        }

        // Log status changes
        if ($submission->wasChanged('status')) {
            $oldStatus = $submission->getOriginal('status');
            $newStatus = $submission->status;

            match ($newStatus) {
                HomeworkSubmission::STATUS_SUBMITTED => $this->logSubmissionEvent($submission, $oldStatus),
                HomeworkSubmission::STATUS_GRADED => HomeworkActivity::log(
                    $submission->id,
                    HomeworkActivity::TYPE_GRADED,
                    auth()->id(),
                    ['grade' => $submission->grade]
                ),
                HomeworkSubmission::STATUS_REVISION_REQUESTED => HomeworkActivity::log(
                    $submission->id,
                    HomeworkActivity::TYPE_REVISION_REQUESTED,
                    auth()->id()
                ),
                default => null,
            };
        }
    }

    /**
     * Log submission or resubmission event
     */
    private function logSubmissionEvent(HomeworkSubmission $submission, ?string $oldStatus): void
    {
        $type = $oldStatus === HomeworkSubmission::STATUS_REVISION_REQUESTED
            ? HomeworkActivity::TYPE_RESUBMITTED
            : HomeworkActivity::TYPE_SUBMITTED;

        HomeworkActivity::log($submission->id, $type, $submission->student_id);
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

    /**
     * Handle the HomeworkSubmission "deleting" event.
     *
     * Removes every file the submission holds from the CDN (s3): student
     * attachments, teacher feedback attachments, and the annotated PDFs/images
     * produced during grading. homework_activities rows are removed via FK
     * cascade.
     */
    public function deleting(HomeworkSubmission $submission): void
    {
        $files = array_merge(
            $submission->attachments ?? [],
            $submission->annotated_files ?? [],
            $submission->annotated_images ?? [],
            $submission->feedback_attachments ?? [],
        );

        foreach ($files as $path) {
            if (is_string($path) && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }
}
