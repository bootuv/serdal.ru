<?php

namespace App\Observers;

use App\Models\Homework;

class HomeworkObserver
{
    /**
     * Handle the Homework "created" event.
     */
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
        if (!empty($homework->attachments)) {
            foreach ($homework->attachments as $path) {
                if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk('s3')->delete($path);
                }
            }
        }
    }
}
