<?php

namespace App\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\FileUploadController;

/**
 * Приём временных файлов Livewire (wire:model / FileUpload в Filament).
 *
 * Livewire встраивает base64 от исходного имени в имя временного файла.
 * Для длинных русских названий (~90 символов, 2 байта на символ) имя
 * получается длиннее 255 байт — лимит ext4. Запись падает, Livewire
 * возвращает пустой путь, а компонент затем валится с 500
 * (UnableToRetrieveMetadata: livewire-tmp). Здесь исходное имя укорачиваем
 * так, чтобы имя временного файла гарантированно влезло в лимит,
 * а неудачную запись превращаем в понятную ошибку валидации.
 *
 * Подключается через привязку в AppServiceProvider.
 */
class LivewireFileUploadController extends FileUploadController
{
    /** Лимит длины имени файла в байтах у ext4/xfs/apfs */
    public const MAX_FILENAME_BYTES = 255;

    public function validateAndStore($files, $disk)
    {
        Validator::make(['files' => $files], [
            'files.*' => FileUploadConfiguration::rules(),
        ])->validate();

        $directory = FileUploadConfiguration::path();

        return collect($files)->map(function (UploadedFile $file, int $index) use ($disk, $directory) {
            $filename = static::temporaryFileName($file);

            $stored = $file->storeAs('/' . $directory, $filename, ['disk' => $disk]);

            if ($stored === false) {
                throw ValidationException::withMessages([
                    "files.{$index}" => 'Не удалось сохранить файл на сервере. Попробуйте ещё раз.',
                ]);
            }

            // Livewire ждёт только имя файла без каталога
            return str_replace(FileUploadConfiguration::path('/'), '', $stored);
        })->values()->all();
    }

    /**
     * Имя временного файла в формате Livewire:
     * {30 случайных символов}-meta{base64(исходное имя)}-.{расширение},
     * при этом общая длина не превышает MAX_FILENAME_BYTES.
     */
    public static function temporaryFileName(UploadedFile $file): string
    {
        $hash = Str::random(30);
        $extension = $file->getClientOriginalExtension();

        // Livewire меняет «/» на «_» в base64, чтобы не ломать путь
        $meta = fn (string $name) => str_replace('/', '_', '-meta' . base64_encode($name) . '-');

        $budget = static::MAX_FILENAME_BYTES - strlen($hash) - strlen('.' . $extension);

        return $hash . $meta(static::fitOriginalName($file->getClientOriginalName(), $budget, $meta)) . '.' . $extension;
    }

    /**
     * Укорачивает исходное имя (сохраняя расширение), пока его кодированный
     * вид не уложится в $budget байт. Режет по символам, а не по байтам,
     * чтобы не оставить обрывок многобайтового символа.
     */
    protected static function fitOriginalName(string $name, int $budget, callable $encode): string
    {
        if (strlen($encode($name)) <= $budget) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';
        $base = $extension !== '' ? mb_substr($name, 0, -mb_strlen($suffix)) : $name;

        while (mb_strlen($base) > 1 && strlen($encode($base . $suffix)) > $budget) {
            $base = mb_substr($base, 0, -1);
        }

        $shortened = $base . $suffix;

        // Расширение само по себе не влезает — оставляем только обрезанную основу
        if (strlen($encode($shortened)) > $budget) {
            $shortened = $base;

            while (mb_strlen($shortened) > 1 && strlen($encode($shortened)) > $budget) {
                $shortened = mb_substr($shortened, 0, -1);
            }
        }

        return $shortened;
    }
}
