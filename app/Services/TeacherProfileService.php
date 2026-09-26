<?php

namespace App\Services;

use App\Helpers\FileUploadHelper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Профиль учителя: классы, сохранение (фото, предметы, направления, пароль).
 * Используется в Cabinet\Teacher\Profile и Onboarding.
 */
class TeacherProfileService
{
    /** С кем занимается учитель (`grade` — json-массив строк). */
    public const GRADES = [
        'preschool' => 'Дошкольники',
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
        'adults' => 'Взрослые',
    ];

    /** Классы из БД — строками, в порядке GRADES, без неизвестных значений. */
    public static function gradesForForm(mixed $stored): array
    {
        $stored = array_map('strval', is_array($stored) ? $stored : []);

        return array_values(array_filter(array_map('strval', array_keys(self::GRADES)), fn($g) => in_array($g, $stored, true)));
    }

    /**
     * Сохранить профиль. avatar: загруженный файл (сожмём до 640×640), путь в S3 или null — удалить фото;
     * ключа нет — фото не меняется. subjects/directs — id для синхронизации (ключа нет — не трогаем).
     * Пустой пароль не меняется; после смены пароля пользователь остаётся в системе.
     */
    public function update(User $user, array $data): void
    {
        $oldAvatar = $user->avatar;
        $subjects = $data['subjects'] ?? null;
        $directs = $data['directs'] ?? null;
        unset($data['subjects'], $data['directs']);

        if (array_key_exists('avatar', $data)) {
            $files = is_object($data['avatar']) ? [$data['avatar']] : $data['avatar'];
            $processed = FileUploadHelper::processFiles($files, 'avatars', 640, 640);
            $data['avatar'] = $processed[0] ?? null;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        if ($subjects !== null) {
            $user->subjects()->sync($subjects);
        }
        if ($directs !== null) {
            $user->directs()->sync($directs);
        }

        // Старое фото больше не нужно
        if (array_key_exists('avatar', $data) && $oldAvatar && $oldAvatar !== $user->avatar) {
            try {
                Storage::disk('s3')->delete($oldAvatar);
            } catch (\Throwable $e) {
                \Log::error('TeacherProfileService: failed to delete old avatar - ' . $e->getMessage());
            }
        }

        // После смены пароля входим заново, чтобы не потерять сессию
        if (isset($data['password'])) {
            Auth::login($user);
        }
    }
}
