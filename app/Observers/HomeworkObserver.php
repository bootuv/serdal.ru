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
     * Handle the Homework "deleting" event.
     *
     * Runs before the row is removed. We delete the homework's own attachments
     * from the CDN (s3) and explicitly delete each submission so that its own
     * deleting event fires and cleans up submission files. The database-level
     * FK cascade would remove submission rows silently, bypassing Eloquent
     * events and leaking their files on the CDN.
     */
    public function deleting(Homework $homework): void
    {
        // Delete homework attachments from the CDN
        foreach ($homework->attachments ?? [] as $path) {
            if (is_string($path) && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }

        // Delete submissions one by one to trigger per-submission file cleanup.
        // homework_student / homework_activities rows are removed via FK cascade.
        $homework->submissions()->cursor()->each(fn ($submission) => $submission->delete());
    }
}
