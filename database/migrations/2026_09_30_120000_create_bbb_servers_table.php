<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Несколько серверов видеосвязи (BBB) вместо одного адреса в настройках.
 * Занятие, проведённое занятие и запись помнят свой сервер. Общий сервер из настроек
 * и личные серверы учителей (users.bbb_url) переносятся в таблицу.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bbb_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            // Алгоритм подписи запросов: подбирается проверкой (новые версии BBB могут не принимать sha1)
            $table->string('checksum', 10)->default('sha1');
            $table->string('version')->nullable();
            // Вместимость, участников: вес при распределении занятий
            $table->unsignedInteger('capacity')->default(100);
            $table->boolean('is_enabled')->default(true);
            // Личный сервер учителя: занятия этого учителя идут только на нём, другим не достаётся
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Последняя проверка
            $table->boolean('is_online')->default(true);
            $table->unsignedInteger('meetings')->default(0);
            $table->unsignedInteger('participants')->default(0);
            // Участники по каждому идущему занятию: {meetingID: сколько сейчас} — для распределения
            $table->json('meeting_loads')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        foreach (['rooms', 'meeting_sessions', 'recordings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('bbb_server_id')->nullable()->constrained('bbb_servers')->nullOnDelete();
            });
        }

        $this->moveExistingServers();
    }

    private function moveExistingServers(): void
    {
        $now = now();
        $insert = fn (string $url, string $secret, ?int $userId) => DB::table('bbb_servers')->insertGetId([
            'name' => parse_url($url, PHP_URL_HOST) ?: $url,
            'url' => $url,
            'secret' => Crypt::encryptString($secret),
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $url = trim((string) DB::table('settings')->where('key', 'bbb_url')->value('value'));
        $secret = trim((string) DB::table('settings')->where('key', 'bbb_secret')->value('value'));
        $default = $url !== '' && $secret !== '' ? $insert($url, $secret, null) : null;

        // Личные серверы учителей; совпадающий с общим — просто общий
        $personal = [];
        if (Schema::hasColumn('users', 'bbb_url')) {
            foreach (DB::table('users')->whereNotNull('bbb_url')->where('bbb_url', '!=', '')->whereNotNull('bbb_secret')->get(['id', 'bbb_url', 'bbb_secret']) as $user) {
                if ($default && rtrim($user->bbb_url, '/') === rtrim($url, '/')) {
                    continue;
                }
                $personal[$user->id] = $insert(trim($user->bbb_url), trim($user->bbb_secret), $user->id);
            }
        }

        // Занятия, проведённые занятия и записи — на сервер своего учителя
        foreach (DB::table('rooms')->get(['id', 'user_id', 'meeting_id']) as $room) {
            $serverId = $personal[$room->user_id] ?? $default;
            if (! $serverId) {
                continue;
            }
            DB::table('rooms')->where('id', $room->id)->update(['bbb_server_id' => $serverId]);
            DB::table('meeting_sessions')->where('room_id', $room->id)->update(['bbb_server_id' => $serverId]);
            if ($room->meeting_id) {
                DB::table('recordings')->where('meeting_id', $room->meeting_id)->update(['bbb_server_id' => $serverId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['rooms', 'meeting_sessions', 'recordings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('bbb_server_id');
            });
        }

        Schema::dropIfExists('bbb_servers');
    }
};
