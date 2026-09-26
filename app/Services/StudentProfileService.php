<?php

namespace App\Services;

use App\Helpers\FileUploadHelper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Профиль ученика: варианты класса и сохранение (аватар, пароль).
 * Используется старым кабинетом (Filament Student\Pages\Profile) и новым профилем.
 */
class StudentProfileService
{
    /** Класс ученика. В БД хранится массивом (`grade` — json), в форме — одно значение. */
    public const GRADES = [
        'preschool' => 'Дошкольник',
        '1' => '1 класс',
        '2' => '2 класс',
        '3' => '3 класс',
        '4' => '4 класс',
        '5' => '5 класс',
        '6' => '6 класс',
        '7' => '7 класс',
        '8' => '8 класс',
        '9' => '9 класс',
        '10' => '10 класс',
        '11' => '11 класс',
        'adults' => 'Взрослый',
    ];

    /** Значение для формы из массива в БД. */
    public static function gradeForForm(mixed $stored): ?string
    {
        if (! is_array($stored) || empty($stored)) {
            return null;
        }

        return (string) $stored[0];
    }

    /** Значение для БД из формы: число — int, остальное — строкой, всё в массиве. */
    public static function gradeForStorage(mixed $state): array
    {
        if (empty($state)) {
            return [];
        }

        return [is_numeric($state) ? (int) $state : $state];
    }

    /**
     * Сохранить профиль. avatar — путь в S3 или загруженный файл (сожмём до 640×640 и положим в avatars/{id}).
     * Пустой пароль не меняется; после смены пароля пользователь остаётся в системе.
     */
    public function update(User $user, array $data): void
    {
        $oldAvatar = $user->avatar;

        if (array_key_exists('avatar', $data)) {
            // Одиночный файл — в массив: processFiles приводит аргумент к массиву
            $files = is_object($data['avatar']) ? [$data['avatar']] : $data['avatar'];
            $processed = FileUploadHelper::processFiles($files, 'avatars', 640, 640);
            $data['avatar'] = $processed[0] ?? null;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Старое фото больше не нужно
        if (array_key_exists('avatar', $data) && $oldAvatar && $oldAvatar !== $user->avatar) {
            try {
                Storage::disk('s3')->delete($oldAvatar);
            } catch (\Throwable $e) {
                \Log::error('StudentProfileService: failed to delete old avatar - ' . $e->getMessage());
            }
        }

        if (isset($data['password'])) {
            Auth::login($user);
        }
    }
}
