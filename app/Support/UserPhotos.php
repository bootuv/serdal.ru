<?php

namespace App\Support;

use App\Models\User;

/**
 * Фото людей для x-ui.avatar, когда под рукой только id (снимок участников проведённого занятия, участники BBB).
 * Один запрос на всех ещё не известных; живёт один запрос или одну задачу очереди (scoped), чтобы новое фото не залипло.
 */
class UserPhotos
{
    /** @var array<int, ?string> id => адрес уменьшенного фото или null */
    private array $known = [];

    /** Загрузить фото заранее — например, для всех строк списка, чтобы дальше of() не ходил в базу. */
    public function load(iterable $ids): void
    {
        $missing = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()
            ->reject(fn (int $id) => array_key_exists($id, $this->known))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        $photos = User::whereIn('id', $missing)->whereNotNull('avatar')->where('avatar', '!=', '')
            ->get(['id', 'avatar'])
            ->mapWithKeys(fn (User $u) => [$u->id => $u->photoThumb()]);

        foreach ($missing as $id) {
            $this->known[$id] = $photos[$id] ?? null;
        }
    }

    public function of(?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $this->load([$id]);

        return $this->known[$id];
    }
}
