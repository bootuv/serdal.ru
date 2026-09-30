<?php

namespace App\Services\Bbb;

use App\Models\BbbServer;
use App\Models\MeetingSession;
use App\Models\Recording;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Какой сервер видеосвязи обслуживает занятие.
 *
 * Новое занятие (pick): личный сервер учителя, если есть; иначе общий сервер с наименьшей долей занятых мест
 * (ожидаемые участники / вместимость). Наше идущее занятие считается по большему из «сколько в нём сейчас»
 * (проверка сервера) и «сколько ждём» (ученики занятия + учитель): участники подключаются не сразу, и только что
 * начатое групповое занятие иначе выглядело бы пустым. Чужие занятия на сервере — по факту из проверки.
 * Серверы не на связи пропускаются, пока есть хоть один на связи. Идущее занятие и его записи
 * всегда обслуживает сервер, на котором занятие создано (rooms/meeting_sessions/recordings.bbb_server_id).
 *
 * Пока в таблице нет ни одного сервера, работает сервер из .env (BBB_SERVER_BASE_URL) — для разработки.
 */
class BbbServerPool
{
    /** Меньше стольких участников в занятии не ждём: учитель и хотя бы один ученик или гость. */
    private const MIN_EXPECTED = 2;

    /**
     * Сервер для нового занятия этой комнаты. $exclude — серверы, на которых создать занятие не вышло
     * (перебор при сбое): личный сервер учителя тогда не подменяется общим.
     */
    public function pick(Room $room, array $exclude = []): BbbServer
    {
        if ($personal = $this->personal($room->user)) {
            if (in_array($personal->id, $exclude, true)) {
                throw new NoBbbServerException('Личный сервер учителя не отвечает.');
            }

            return $personal;
        }

        $candidates = BbbServer::shared()->where('is_enabled', true)->whereNotIn('id', $exclude)->get();

        if ($candidates->isEmpty()) {
            return ($exclude ? null : $this->fallback()) ?? throw new NoBbbServerException('Нет ни одного включённого сервера видеосвязи.');
        }

        $online = $candidates->where('is_online', true);
        $pool = $online->isNotEmpty() ? $online : $candidates;

        $loads = $this->loads($pool);

        return $pool
            ->sortBy([
                fn (BbbServer $a, BbbServer $b) => $this->share($a, $loads, 'participants') <=> $this->share($b, $loads, 'participants'),
                fn (BbbServer $a, BbbServer $b) => $this->share($a, $loads, 'meetings') <=> $this->share($b, $loads, 'meetings'),
                fn (BbbServer $a, BbbServer $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    /** Сервер, на котором идёт (или шло последним) занятие комнаты. */
    public function forRoom(Room $room): ?BbbServer
    {
        if ($room->bbb_server_id && ($server = BbbServer::find($room->bbb_server_id))) {
            return $server;
        }

        return $this->personal($room->user) ?? $this->defaultServer();
    }

    /** Сервер, на котором лежит запись. */
    public function forRecording(Recording $recording): ?BbbServer
    {
        if ($recording->bbb_server_id && ($server = BbbServer::find($recording->bbb_server_id))) {
            return $server;
        }

        $room = Room::withTrashed()->where('meeting_id', $recording->meeting_id)->first();

        return $room ? $this->forRoom($room) : $this->defaultServer();
    }

    /** Серверы, где могут быть записи занятий учителя: его личный и все, где шли его занятия. */
    public function forTeacher(User $teacher): Collection
    {
        $ids = MeetingSession::where('user_id', $teacher->id)->whereNotNull('bbb_server_id')->distinct()->pluck('bbb_server_id')
            ->merge(Room::withTrashed()->where('user_id', $teacher->id)->whereNotNull('bbb_server_id')->distinct()->pluck('bbb_server_id'))
            ->unique();

        $servers = BbbServer::whereIn('id', $ids)->orWhere('user_id', $teacher->id)->get();

        return $servers->isNotEmpty() ? $servers : collect(array_filter([$this->defaultServer()]));
    }

    /** Все серверы для проверки и сверки (сервер из .env — если таблица пуста). */
    public function all(): Collection
    {
        $servers = BbbServer::orderBy('id')->get();

        return $servers->isNotEmpty() ? $servers : collect(array_filter([$this->fallback()]));
    }

    /**
     * Ожидаемая нагрузка серверов: занятия и участники.
     *
     * @return array<int, array{meetings:int, participants:int}>
     */
    public function loads(Collection $servers): array
    {
        // Наши идущие занятия: сколько участников ждём в каждом
        $expected = [];
        MeetingSession::whereIn('bbb_server_id', $servers->pluck('id'))
            ->where('status', 'running')
            ->with(['room' => fn ($q) => $q->withCount('participants')])
            ->get(['id', 'room_id', 'bbb_server_id', 'meeting_id'])
            ->each(function (MeetingSession $session) use (&$expected) {
                $expected[$session->bbb_server_id][(string) $session->meeting_id] = max(self::MIN_EXPECTED, (int) ($session->room?->participants_count ?? 0) + 1);
            });

        $result = [];
        foreach ($servers as $server) {
            // Сейчас на сервере, по последней проверке (и чужие занятия тоже)
            $actual = collect($server->meeting_loads ?? [])->mapWithKeys(fn ($n, $id) => [(string) $id => (int) $n])->all();
            $ours = $expected[$server->id] ?? [];

            $participants = 0;
            foreach (array_keys($actual + $ours) as $meetingId) {
                $participants += max($actual[$meetingId] ?? 0, $ours[$meetingId] ?? 0);
            }

            $result[$server->id] = ['meetings' => count($actual + $ours), 'participants' => $participants];
        }

        return $result;
    }

    /** Доля занятых мест (или занятий) на сервере — на единицу вместимости. */
    private function share(BbbServer $server, array $loads, string $what): float
    {
        return ($loads[$server->id][$what] ?? 0) / max(1, $server->capacity);
    }

    private function personal(?User $teacher): ?BbbServer
    {
        return $teacher ? BbbServer::where('user_id', $teacher->id)->where('is_enabled', true)->first() : null;
    }

    /** Сервер по умолчанию для старых данных без сервера: первый общий или сервер из .env. */
    private function defaultServer(): ?BbbServer
    {
        return BbbServer::shared()->orderByDesc('is_enabled')->orderBy('id')->first() ?? $this->fallback();
    }

    /** Сервер из .env — только пока в таблице нет ни одного сервера. */
    private function fallback(): ?BbbServer
    {
        $url = trim((string) config('bigbluebutton.BBB_SERVER_BASE_URL'));
        $secret = trim((string) config('bigbluebutton.BBB_SECURITY_SALT'));

        if ($url === '' || $secret === '' || BbbServer::exists()) {
            return null;
        }

        return new BbbServer([
            'name' => parse_url($url, PHP_URL_HOST) ?: $url,
            'url' => $url,
            'secret' => $secret,
            'checksum' => config('bigbluebutton.hash_algorithm', 'sha1'),
            'capacity' => 100,
            'is_enabled' => true,
            'is_online' => true,
        ]);
    }
}
