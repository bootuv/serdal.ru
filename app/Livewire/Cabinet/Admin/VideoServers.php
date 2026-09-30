<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Models\BbbServer;
use App\Models\Room;
use App\Services\Bbb\BbbServerMonitor;
use Livewire\Component;

/**
 * Серверы видеосвязи (вкладка «Видеосвязь» в настройках): список с нагрузкой и состоянием, окно сервера,
 * удаление. Новые занятия распределяются по общим серверам по нагрузке (BbbServerPool), состояние
 * обновляет проверка раз в минуту (bbb:check-servers) и сохранение сервера здесь.
 */
class VideoServers extends Component
{
    use AdminScreen;

    /** Открытое окно: null — закрыто, 0 — новый сервер, id — сервер. */
    public ?int $serverId = null;

    public string $name = '';

    public string $url = '';

    /** Секретный ключ: у существующего сервера пустое поле — ключ не меняется. */
    public string $secret = '';

    public string $capacity = '100';

    public bool $enabled = true;

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeAdmin();
        $this->resetValidation();

        $server = $id ? BbbServer::findOrFail($id) : null;

        $this->serverId = $server?->id ?? 0;
        $this->name = (string) $server?->name;
        $this->url = (string) $server?->url;
        $this->secret = '';
        $this->capacity = (string) ($server?->capacity ?? 100);
        $this->enabled = $server?->is_enabled ?? true;
    }

    public function close(): void
    {
        $this->serverId = null;
        $this->resetValidation();
    }

    public function save(BbbServerMonitor $monitor): void
    {
        $this->authorizeAdmin();

        $this->url = trim($this->url);
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:255'],
            'secret' => [$this->serverId ? 'nullable' : 'required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'name.required' => 'Назовите сервер — так его будет видно в списке',
            'url.required' => 'Укажите адрес сервера',
            'url.url' => 'Введите адрес целиком, вместе с https://',
            'secret.required' => 'Укажите секретный ключ сервера',
            'capacity.*' => 'Укажите, сколько участников сервер выдерживает одновременно',
        ]);

        $server = $this->serverId ? BbbServer::findOrFail($this->serverId) : new BbbServer();
        $server->fill([
            'name' => trim($this->name),
            'url' => $this->url,
            'capacity' => (int) $this->capacity,
            'is_enabled' => $this->enabled,
        ]);
        if (trim($this->secret) !== '') {
            $server->secret = trim($this->secret);
        }
        $server->save();

        // Сразу проверяем: ключ, версия, подходящий алгоритм подписи
        $monitor->check($server);

        $this->serverId = null;
        $this->dispatch('toast', ...$server->is_online
            ? ['message' => 'Сервер сохранён и на связи' . ($server->version ? ' · версия ' . $server->version : '')]
            : ['message' => 'Сервер сохранён, но проверка не прошла: ' . mb_strtolower($server->error ?? 'сервер не отвечает'), 'tone' => 'danger']);
    }

    public function askDelete(int $id): void
    {
        $this->authorizeAdmin();
        $this->serverId = null;
        $this->deletingId = BbbServer::findOrFail($id)->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $this->authorizeAdmin();

        $server = BbbServer::findOrFail($this->deletingId);
        if ($this->runningOn($server->id) > 0) {
            return;
        }

        $server->delete();
        $this->deletingId = null;
        $this->dispatch('toast', message: 'Сервер удалён');
    }

    private function runningOn(int $serverId): int
    {
        return Room::where('bbb_server_id', $serverId)->where('is_running', true)->count();
    }

    public function render()
    {
        $servers = BbbServer::with('user:id,name')->orderByRaw('user_id is not null')->orderBy('id')->get()
            ->map(fn (BbbServer $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sub' => collect([
                    $s->host(),
                    $s->version ? 'версия ' . $s->version : null,
                    $s->user ? 'только для учителя ' . $s->user->name : null,
                ])->filter()->join(' · '),
                'load' => match (true) {
                    ! $s->checked_at => 'Ещё не проверяли',
                    ! $s->is_online => $s->error ?? 'Сервер не отвечает',
                    default => ($s->meetings ? plural_ru($s->meetings, 'занятие', 'занятия', 'занятий') : 'Занятий нет')
                        . ' · ' . $s->participants . ' из ' . plural_ru($s->capacity, 'участника', 'участников', 'участников'),
                },
                'offline' => $s->checked_at && ! $s->is_online,
                'enabled' => $s->is_enabled,
            ]);

        $editing = $this->serverId ? BbbServer::with('user:id,name')->find($this->serverId) : null;
        $deleting = $this->deletingId ? BbbServer::find($this->deletingId) : null;

        return view('livewire.cabinet.admin.video-servers', [
            'servers' => $servers,
            'editing' => $editing,
            'deleting' => $deleting,
            'deletingRunning' => $deleting ? $this->runningOn($deleting->id) : 0,
        ]);
    }
}
