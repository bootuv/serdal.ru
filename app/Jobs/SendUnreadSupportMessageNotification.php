<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUnreadSupportMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SupportMessage $message,
        public $recipient
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Проверяем актуальное состояние сообщения из базы
        if ($this->message->fresh()->read_at === null) {
            $this->recipient->notify(new \App\Notifications\NewSupportMessage($this->message));
        }
    }
}
