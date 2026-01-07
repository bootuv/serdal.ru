<?php

namespace App\Observers;

use App\Models\Homework;
use Illuminate\Support\Facades\Storage;

class HomeworkObserver
{
    /**
     * Handle the Homework "created" event.
     */
    public function created(Homework $homework): void
    {
        // Processing handled in form
    }

    /**
     * Handle the Homework "updated" event.
     */
    public function updated(Homework $homework): void
    {
        // Processing handled in form
    }

    /**
     * Handle the Homework "deleted" event.
     */
    public function deleted(Homework $homework): void
    {
        // Delete homework attachments
        if (!empty($homework->attachments)) {
            foreach ($homework->attachments as $path) {
                if (Storage::disk('s3')->exists($path)) {
                    Storage::disk('s3')->delete($path);
                }
            }
        }

        // Delete all submission files (student attachments + teacher feedback)
        foreach ($homework->submissions as $submission) {
            // Student attachments
            if (!empty($submission->attachments)) {
                foreach ($submission->attachments as $path) {
                    if (Storage::disk('s3')->exists($path)) {
                        Storage::disk('s3')->delete($path);
                    }
                }
            }

            // Teacher feedback attachments
            if (!empty($submission->feedback_attachments)) {
                foreach ($submission->feedback_attachments as $path) {
                    if (Storage::disk('s3')->exists($path)) {
                        Storage::disk('s3')->delete($path);
                    }
                }
            }
        }
    }
}
