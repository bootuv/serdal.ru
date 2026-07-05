<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendSupportMessageTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public SupportMessage $message
    ) {
    }

    public function handle(): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        $sender = $this->message->user;
        $role = $sender?->display_role ?? '';

        $content = trim($this->message->content ?? '');
        if ($content === '' && !empty($this->message->attachments)) {
            $content = '[вложение]';
        }

        try {
            $url = route('filament.admin.pages.admin-messenger', ['chat' => $this->message->support_chat_id]);
        } catch (\Exception) {
            $url = null;
        }

        $text = "💬 <b>Новое сообщение в поддержку</b>\n"
            . '<b>' . e($sender?->name ?? 'Пользователь') . '</b>'
            . ($role !== '' ? ' (' . e($role) . ')' : '') . ":\n"
            . e(Str::limit($content, 300));

        if ($url) {
            $text .= "\n\n<a href=\"{$url}\">Открыть чат</a>";
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram support notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'support_message_id' => $this->message->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram support notification error: ' . $e->getMessage(), [
                'support_message_id' => $this->message->id,
            ]);
        }
    }
}
