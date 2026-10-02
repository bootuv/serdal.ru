<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\EditorMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Чтобы на хранилище не копился мусор: каждый файл из редактора новостей и блога записан (register),
 * при сохранении текста файлы, которых в нем больше нет, удаляются (sync), кнопка «Удалить» в редакторе
 * удаляет несохраненный файл сразу (discard), брошенные загрузки — командой media:cleanup раз в сутки.
 * Перед удалением проверяем, что адрес не встречается в других новостях и статьях. Файлы до появления учета не трогаем.
 */
class EditorMediaService
{
    /** Через сколько удалять файл, который так и не попал ни в один сохраненный текст. */
    public const ABANDONED_AFTER_HOURS = 24;

    /** Записать загруженный файл. $paths — пути на хранилище (по умолчанию — путь из адреса). */
    public function register(string $url, ?User $user = null, array $paths = []): void
    {
        $paths = $paths ?: array_filter([$this->pathFromUrl($url)]);
        if (! $paths) {
            return;
        }
        EditorMedia::updateOrCreate(['url' => $url], ['paths' => array_values($paths), 'user_id' => $user?->id]);
    }

    /**
     * Текст сохранен: файлы из него — его, файлы, которые были его, а теперь не встречаются, — удалить.
     * $content — HTML текста и прочие адреса (обложка).
     */
    public function sync(Model $owner, array $content): void
    {
        $used = $this->urlsIn($content);

        if ($used) {
            EditorMedia::whereIn('url', $used)->update(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()]);
        }

        EditorMedia::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())
            ->when($used, fn ($q) => $q->whereNotIn('url', $used))
            ->get()->each(fn (EditorMedia $media) => $this->purge($media));
    }

    /** Текст удален — удалить его файлы. */
    public function purgeOwner(Model $owner): void
    {
        EditorMedia::where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())
            ->get()->each(fn (EditorMedia $media) => $this->purge($media, except: $owner));
    }

    /**
     * Кнопка «Удалить» в редакторе: файл еще не в сохраненном тексте — удаляем сразу; в сохраненном — удалится,
     * когда текст сохранят без него. Чужие несохраненные загрузки не трогаем. true — удален.
     */
    public function discard(string $url, ?Model $owner, User $user): bool
    {
        $media = EditorMedia::where('url', $url)->first();
        if (! $media) {
            return false;
        }

        $mine = $owner && $media->owner_type === $owner->getMorphClass() && (int) $media->owner_id === (int) $owner->getKey();
        if (! $mine && ($media->owner_id || (int) $media->user_id !== $user->id)) {
            return false;
        }
        if ($mine) {
            return false; // в сохраненном тексте — удалится при сохранении без него
        }

        return $this->purge($media);
    }

    /** Брошенные загрузки: не попали ни в один сохраненный текст за сутки. */
    public function cleanup(): int
    {
        return EditorMedia::whereNull('owner_id')->where('created_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS))
            ->get()->filter(fn (EditorMedia $media) => $this->purge($media))->count();
    }

    /** Удалить файлы с хранилища, если адрес больше нигде не встречается. Видео в обработке — отменить. */
    public function purge(EditorMedia $media, ?Model $except = null): bool
    {
        if ($this->usedElsewhere($media, $except)) {
            $media->update(['owner_type' => null, 'owner_id' => null]);

            return false;
        }

        $disk = Storage::disk(HelpCenterService::DISK);
        foreach ($media->paths as $path) {
            $disk->delete($path);
        }
        // Ролик еще сжимается — задача увидит отметку и не станет выкладывать результат
        Cache::put('media:discarded:' . pathinfo((string) parse_url($media->url, PHP_URL_PATH), PATHINFO_FILENAME), true, now()->addHours(6));
        $media->delete();

        return true;
    }

    /** Адреса картинок и видео (с обложками) из HTML текста; строка без разметки — сама адрес (обложка статьи). */
    public function urlsIn(array $content): array
    {
        $urls = [];
        foreach (array_filter($content) as $item) {
            if (! str_contains($item, '<')) {
                $urls[] = trim($item);
                continue;
            }
            preg_match_all('~(?:src|poster)="([^"]+)"~i', $item, $m);
            array_push($urls, ...array_map('html_entity_decode', $m[1]));
        }

        return array_values(array_unique($urls));
    }

    /** Путь на хранилище по адресу файла; чужой адрес — null. */
    public function pathFromUrl(string $url): ?string
    {
        $base = rtrim(Storage::disk(HelpCenterService::DISK)->url(''), '/') . '/';

        return str_starts_with($url, $base) ? substr($url, strlen($base)) : null;
    }

    /** Адрес встречается в другой новости или статье (скопировали текст) — файл нужен. */
    private function usedElsewhere(EditorMedia $media, ?Model $except): bool
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $media->url) . '%';
        $skip = fn ($model) => fn ($q) => $except instanceof $model ? $q->whereKeyNot($except->getKey()) : $q;
        $owner = $media->owner_type && $media->owner_id ? [$media->owner_type, (int) $media->owner_id] : null;
        $notOwner = fn ($model) => fn ($q) => $owner && $owner[0] === (new $model)->getMorphClass() ? $q->whereKeyNot($owner[1]) : $q;

        return Announcement::where('body', 'like', $like)->tap($skip(Announcement::class))->tap($notOwner(Announcement::class))->exists()
            || BlogPost::where(fn ($q) => $q->where('body', 'like', $like)->orWhere('cover_url', $media->url))
                ->tap($skip(BlogPost::class))->tap($notOwner(BlogPost::class))->exists();
    }
}
