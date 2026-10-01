<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Сообщение в чат техслужбы в Telegram (services.telegram.admin_chat_id) — тем же ботом и через тот же релей/прокси,
 * что заявки учителей и сообщения в поддержку. Текст — HTML (parse_mode=HTML), значения экранировать при сборке.
 * Не настроен бот или чат — ничего не делаем.
 */
class SendAdminTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $text
    ) {
    }

    public function handle(): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        $apiBase = rtrim(config('services.telegram.api_base', 'https://api.telegram.org'), '/');
        $proxy = config('services.telegram.proxy');

        try {
            $client = Http::timeout(10);
            if (filled($proxy)) {
                $client = $client->withOptions(['proxy' => $proxy]);
            }

            $response = $client->post("{$apiBase}/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $this->text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram admin message failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram admin message error: ' . $e->getMessage());
        }
    }
}
