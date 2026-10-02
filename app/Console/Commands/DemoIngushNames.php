<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\IngushNames;
use Illuminate\Console\Command;

/**
 * Демо-данные: переименовать учителей и учеников в ингушские имена (локально). Админов не трогаем.
 * Пол берем из нынешнего имени (отчество «-вна», фамилия на «-а»); учителям — с отчеством, ученикам — фамилия и имя.
 * Адреса страниц (username) не меняются — ссылки продолжают работать.
 */
class DemoIngushNames extends Command
{
    protected $signature = 'demo:ingush-names {--force : Разрешить на проде}';

    protected $description = 'Переименовать тестовых учителей и учеников в ингушские имена';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Это прод — настоящих пользователей не переименовываем. Если очень нужно: --force');

            return self::FAILURE;
        }

        $used = [];
        $n = 0;
        User::whereIn('role', [User::ROLE_TUTOR, User::ROLE_STUDENT])->orderBy('id')->each(function (User $user) use (&$used, &$n) {
            $female = $this->isFemale($user);
            do {
                $parts = IngushNames::random($female, $user->role === User::ROLE_TUTOR);
                $key = implode(' ', array_filter($parts));
            } while (isset($used[$key]));
            $used[$key] = true;

            $user->forceFill($parts)->saveQuietly();
            // Полное имя собирает событие saving модели; saveQuietly его пропускает — собираем сами
            $user->forceFill(['name' => $key])->saveQuietly();
            $n++;
        });

        $this->info("Переименовано: {$n}");

        return self::SUCCESS;
    }

    private function isFemale(User $user): bool
    {
        $middle = mb_strtolower((string) $user->middle_name);
        if ($middle !== '') {
            return str_ends_with($middle, 'на');
        }
        $words = preg_split('/\s+/u', mb_strtolower(trim((string) $user->name)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            if (preg_match('/(вна|чна)$/u', $w)) {
                return true;
            }
            if (preg_match('/(вич|ич)$/u', $w)) {
                return false;
            }
        }
        $last = mb_strtolower((string) ($user->last_name ?: ($words[0] ?? '')));

        return (bool) preg_match('/(ова|ева|ина|ая|ска)$/u', $last);
    }
}
