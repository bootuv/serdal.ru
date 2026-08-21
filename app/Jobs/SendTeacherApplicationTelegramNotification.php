<?php

namespace App\Jobs;

use App\Models\Subject;
use App\Models\TeacherApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTeacherApplicationTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public TeacherApplication $application
    ) {
    }

    public function handle(): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        $application = $this->application;

        $lines = [
            '📝 <b>Новая заявка учителя</b>',
            '<b>' . e($application->full_name) . '</b>',
        ];

        if (filled($application->email)) {
            $lines[] = 'Email: ' . e($application->email);
        }
        if (filled($application->phone)) {
            $lines[] = 'Телефон: ' . e($application->phone);
        }
        if (filled($application->telegram)) {
            $lines[] = 'Telegram: @' . e(ltrim($application->telegram, '@'));
        }

        if (!empty($application->subjects)) {
            $subjects = Subject::whereIn('id', $application->subjects)->pluck('name')->all();
            if ($subjects) {
                $lines[] = 'Предметы: ' . e(implode(', ', $subjects));
            }
        }

        if (filled($application->about)) {
            $lines[] = "\n" . e(Str::limit(trim($application->about), 300));
        }

        try {
            $url = route('filament.admin.resources.teacher-applications.index');
        } catch (\Exception) {
            $url = null;
        }

        $text = implode("\n", $lines);
        if ($url) {
            $text .= "\n\n<a href=\"{$url}\">Открыть заявки</a>";
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
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram teacher application notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'teacher_application_id' => $application->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram teacher application notification error: ' . $e->getMessage(), [
                'teacher_application_id' => $application->id,
            ]);
        }
    }
}
