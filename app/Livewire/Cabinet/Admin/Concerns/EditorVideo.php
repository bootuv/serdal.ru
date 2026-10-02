<?php

namespace App\Livewire\Cabinet\Admin\Concerns;

use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Видео в блочном редакторе (x-ui.block-editor с video-model="video" video-method="storeVideo"): новости и статьи блога.
 * Ролик сжимается в очереди (MediaService), редактор опрашивает mediaStatus; публиковать текст с необработанным видео нельзя.
 * Компоненту нужен WithFileUploads, mediaDir() — папка на CDN и mediaOwner() — сохраненный текст (для удаления файлов).
 */
trait EditorVideo
{
    /** Видео для текста (временная загрузка). */
    public $video = null;

    abstract protected function mediaDir(): string;

    /** Сохраненная новость или статья (null — еще не сохранена). */
    abstract protected function mediaOwner(): ?\Illuminate\Database\Eloquent\Model;

    /** Кнопка «Удалить» у картинки или видео в редакторе: несохраненный файл удаляем с хранилища сразу. */
    public function discardMedia(string $url): void
    {
        app(\App\Services\EditorMediaService::class)->discard($url, $this->mediaOwner(), auth()->user());
    }

    /** Картинка загружена в текст — записываем, чтобы потом не оставить ее мусором. */
    protected function registerMedia(?string $url): ?string
    {
        if ($url) {
            app(\App\Services\EditorMediaService::class)->register($url, auth()->user());
        }

        return $url;
    }

    /** Видео в текст: исходник в очередь на сжатие, редактору — будущие адреса ролика и обложки. */
    public function storeVideo(): ?array
    {
        $media = app(MediaService::class);
        try {
            $this->validateOnly('video', ['video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-matroska,video/3gpp,video/mpeg', 'max:204800']], [
                'video.mimetypes' => 'Нужно видео MP4, MOV, WebM, AVI или MKV',
                'video.max' => 'Видео больше 200 МБ',
            ]);
            if (! $media->available()) {
                throw ValidationException::withMessages(['video' => 'На сервере не настроена обработка видео']);
            }
        } catch (ValidationException $e) {
            $this->video = null;
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?: 'Видео не удалось загрузить', tone: 'danger');

            return null;
        }

        $result = $media->storeVideo($this->video, $this->mediaDir());
        $this->video = null;

        return $result;
    }

    /**
     * Редактор спрашивает, готово ли видео: status — ready, pending или failed; progress — сколько процентов обработано.
     *
     * @return array{status: string, progress: int}
     */
    public function mediaStatus(string $url): array
    {
        $media = app(MediaService::class);

        return ['status' => $media->status($url), 'progress' => $media->progress($url)];
    }

    /** GIF — в беззвучное зацикленное видео (в разы легче); null — не GIF или ffmpeg нет, грузим картинкой. */
    protected function gifAsVideo(UploadedFile $file): ?array
    {
        $media = app(MediaService::class);
        if (strtolower($file->getClientOriginalExtension()) !== 'gif' || ! $media->available()) {
            return null;
        }

        return $media->storeVideo($file, $this->mediaDir(), loop: true);
    }

    /** Видео еще сжимается или не сжалось — не публикуем (читатели увидели бы пустой плеер). */
    protected function ensureMediaReady(?string $html, string $field = 'body'): void
    {
        $media = app(MediaService::class);
        if ($media->pendingIn($html)) {
            throw ValidationException::withMessages([$field => 'Видео еще обрабатывается — подождите пару минут и опубликуйте снова']);
        }
        if ($media->failedIn($html)) {
            throw ValidationException::withMessages([$field => 'Одно из видео не удалось обработать — удалите его из текста и загрузите снова']);
        }
    }
}
