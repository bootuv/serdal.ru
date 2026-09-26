<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tariff;
use App\Models\User;
use App\Support\HumanDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Пользователи в админке (/cabinet/admin/users): список с поиском и фильтрами, добавление,
 * блокировка, скрытие из каталога, удаление, ссылка для входа. Экраны App\Livewire\Cabinet\Admin\Users и User.
 * Оплату занятий ученик → учитель админка не показывает (решение владельца).
 */
class AdminUserService
{
    /** Вкладки списка → роли. */
    public const TABS = ['teachers' => User::ROLE_TUTOR, 'students' => User::ROLE_STUDENT, 'admins' => User::ROLE_ADMIN];

    /** Классы в фильтре учителей: значение в БД → подпись чипа. */
    public const GRADES = ['preschool' => 'Дошкольники', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6',
        '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', 'adults' => 'Взрослые'];

    /** Сколько людей в каждой вкладке. */
    public function counts(): array
    {
        $byRole = User::query()->select('role', DB::raw('count(*) as n'))->groupBy('role')->pluck('n', 'role');

        return collect(self::TABS)->map(fn (string $role) => (int) ($byRole[$role] ?? 0))->all();
    }

    /** Новых пользователей за неделю — строка фактов под заголовком. */
    public function newThisWeek(): int
    {
        return User::where('created_at', '>=', now()->subWeek())->count();
    }

    /**
     * Люди вкладки с поиском и фильтрами, новые сверху.
     * $filters: subject, direct (id), tariff (id или 'none'), grades (значения GRADES), onboarding (bool), noTeacher (bool).
     */
    public function query(string $tab, string $search = '', array $filters = []): Builder
    {
        $query = User::query()->where('role', self::TABS[$tab] ?? User::ROLE_TUTOR);

        $search = trim($search);
        if ($search !== '') {
            // В MySQL сравнение без учёта регистра даёт сортировка столбца; SQLite так умеет только с латиницей —
            // поэтому ищем и как ввели, и строчными, и с заглавной
            $variants = array_unique([$search, mb_strtolower($search), mb_convert_case($search, MB_CASE_TITLE)]);
            $digits = preg_replace('/\D/', '', $search);
            $query->where(function (Builder $q) use ($variants, $digits) {
                foreach ($variants as $v) {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $v) . '%';
                    $q->orWhere('name', 'like', $like)->orWhere('email', 'like', $like);
                }
                // Телефон ищем по цифрам: «916 245» найдёт «+7 (916) 245-18-73»
                if (strlen($digits) >= 3) {
                    $q->orWhereRaw("replace(replace(replace(replace(replace(coalesce(phone, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') like ?", ['%' . $digits . '%']);
                }
            });
        }

        if ($tab === 'teachers') {
            $query
                ->when($filters['subject'] ?? null, fn ($q, $id) => $q->whereHas('subjects', fn ($s) => $s->where('subjects.id', (int) $id)))
                ->when($filters['direct'] ?? null, fn ($q, $id) => $q->whereHas('directs', fn ($s) => $s->where('directs.id', (int) $id)))
                ->when($filters['onboarding'] ?? false, fn ($q) => $q->where('is_profile_completed', false))
                ->when($filters['grades'] ?? [], function ($q, array $grades) {
                    // Хотя бы один из выбранных классов; в БД классы бывают и строками, и числами
                    $q->where(function (Builder $g) use ($grades) {
                        foreach ($grades as $grade) {
                            $g->orWhereJsonContains('grade', (string) $grade);
                            if (ctype_digit((string) $grade)) {
                                $g->orWhereJsonContains('grade', (int) $grade);
                            }
                        }
                    });
                });

            $tariff = $filters['tariff'] ?? null;
            if ($tariff === 'none') {
                $query->whereDoesntHave('subscriptions', fn ($s) => $s->active());
            } elseif ($tariff) {
                $query->whereHas('subscriptions', fn ($s) => $s->active()->where('tariff_id', (int) $tariff));
            }
        }

        if ($tab === 'students' && ($filters['noTeacher'] ?? false)) {
            $query->whereDoesntHave('teachers');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Строки вкладки «Учителя»: тариф и срок, число учеников, исключения. */
    public function teacherRows(Collection $users): Collection
    {
        $users->load(['subjects:id,name', 'subscriptions' => fn ($q) => $q->with('tariff')->orderByDesc('starts_at')]);
        $students = DB::table('teacher_student')->whereIn('teacher_id', $users->pluck('id'))
            ->select('teacher_id', DB::raw('count(*) as n'))->groupBy('teacher_id')->pluck('n', 'teacher_id');

        return $users->map(function (User $u) use ($students) {
            $tariff = $this->tariffCell($u->subscriptions);

            return [
                'id' => $u->id,
                'user' => $u,
                'name' => $u->name,
                'sub' => implode(' · ', array_filter([$u->email, mb_strtolower($u->subjects->pluck('name')->implode(', '))])),
                'tariff' => $tariff['name'],
                'tariffNone' => $tariff['none'],
                'term' => $tariff['term'],
                'termUrgent' => $tariff['urgent'],
                'students' => (int) ($students[$u->id] ?? 0),
                'marks' => array_values(array_filter([
                    $u->is_blocked ? ['label' => 'Заблокирован', 'tone' => 'danger'] : null,
                    ! $u->is_active ? ['label' => 'Скрыт из каталога', 'tone' => 'neutral'] : null,
                    ! $u->is_profile_completed ? ['label' => 'Первые шаги не пройдены', 'tone' => 'neutral'] : null,
                ])),
            ];
        });
    }

    /** Тариф в строке списка: название и срок («до 12 октября», «закончится завтра», «бессрочно, без оплаты»). */
    private function tariffCell(Collection $subscriptions): array
    {
        $now = now();
        $active = $subscriptions->first(fn (Subscription $s) => $s->status === Subscription::STATUS_ACTIVE && $s->starts_at <= $now && ($s->ends_at === null || $s->ends_at->gt($now)));

        if (! $active || ! $active->tariff) {
            $last = $subscriptions->filter(fn (Subscription $s) => $s->ends_at && $s->ends_at->lte($now))->sortByDesc('ends_at')->first();

            return ['name' => 'Без тарифа', 'none' => true, 'term' => $last ? 'закончился ' . HumanDate::date($last->ends_at) : null, 'urgent' => false];
        }

        $free = $active->tariff->isFree();
        $complimentary = $active->isComplimentary();
        $term = match (true) {
            $free => null,
            $active->ends_at === null => 'бессрочно' . ($complimentary ? ', без оплаты' : ''),
            $active->ends_at->isToday() => 'закончится сегодня',
            $active->ends_at->isTomorrow() => 'закончится завтра',
            default => 'до ' . HumanDate::date($active->ends_at) . ($complimentary ? ', без оплаты' : ''),
        };

        return ['name' => $active->tariff->name, 'none' => false, 'term' => $term, 'urgent' => $active->ends_at && ! $free && $active->ends_at->lte($now->copy()->addDay()->endOfDay())];
    }

    /** Строки вкладки «Ученики»: класс, учителя, последнее занятие. */
    public function studentRows(Collection $users): Collection
    {
        $users->load('teachers:id,name');
        $last = MeetingSession::query()
            ->join('room_user', 'room_user.room_id', '=', 'meeting_sessions.room_id')
            ->whereIn('room_user.user_id', $users->pluck('id'))
            ->where('meeting_sessions.status', 'completed')
            ->whereNotNull('meeting_sessions.started_at')
            ->select('room_user.user_id', DB::raw('max(meeting_sessions.started_at) as last_at'))
            ->groupBy('room_user.user_id')
            ->pluck('last_at', 'room_user.user_id');

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'user' => $u,
            'name' => $u->name,
            'sub' => implode(' · ', array_filter([$u->email, self::studentGrade($u)])),
            'teachers' => $u->teachers->pluck('name')->implode(', '),
            'last' => isset($last[$u->id]) ? HumanDate::at(\Illuminate\Support\Carbon::parse($last[$u->id])) : null,
            'marks' => $u->is_blocked ? [['label' => 'Заблокирован', 'tone' => 'danger']] : [],
        ]);
    }

    /** Строки вкладки «Администраторы»: последний вход. */
    public function adminRows(Collection $users, User $me): Collection
    {
        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'user' => $u,
            'name' => $u->name,
            'sub' => $u->email,
            'seen' => $u->id === $me->id ? 'Это вы' : ($u->last_login_at ? HumanDate::at($u->last_login_at) : 'ещё не входил'),
            'marks' => $u->is_blocked ? [['label' => 'Заблокирован', 'tone' => 'danger']] : [],
        ]);
    }

    /** Класс ученика строчными: «9 класс», «взрослый», «дошкольник». */
    public static function studentGrade(User $u): ?string
    {
        $grade = StudentProfileService::gradeForForm($u->grade);

        return $grade !== null && isset(StudentProfileService::GRADES[$grade]) ? mb_strtolower(StudentProfileService::GRADES[$grade]) : null;
    }

    /** Варианты фильтра «Тариф»: тарифы в продаже и «Без тарифа». */
    public function tariffOptions(): array
    {
        return Tariff::active()->pluck('name', 'id')->map(fn ($n) => (string) $n)->all() + ['none' => 'Без тарифа'];
    }

    /*
     | Действия
     */

    /**
     * Добавить пользователя. $sendLink — письмо со ссылкой, по которой человек сам задаст пароль;
     * иначе пароль задаёт администратор ($password). Учитель начинает с «Первых шагов».
     */
    public function create(string $role, string $lastName, string $firstName, ?string $middleName, string $email, bool $sendLink, ?string $password = null): User
    {
        $user = User::create([
            'last_name' => trim($lastName),
            'first_name' => trim($firstName),
            'middle_name' => trim((string) $middleName) ?: null,
            'email' => mb_strtolower(trim($email)),
            'password' => Hash::make($sendLink ? Str::password(24) : (string) $password),
            'role' => $role,
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => $role !== User::ROLE_TUTOR,
        ]);

        if ($sendLink) {
            $this->sendPasswordLink($user);
        }

        return $user;
    }

    /** Письмо со ссылкой для смены пароля (как «Забыли пароль?»). true — отправлено, false — уже отправляли только что. */
    public function sendPasswordLink(User $user): bool
    {
        return Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
    }

    /** Заблокировать или разблокировать: заблокированного выкидывает из системы (CheckUserActive). Себя — нельзя. */
    public function setBlocked(User $target, bool $blocked, User $admin): void
    {
        abort_if($target->id === $admin->id, 403);

        $target->forceFill(['is_blocked' => $blocked])->save();

        if ($blocked) {
            // Закрываем все открытые сеансы: при следующем запросе человек окажется на экране «Доступ приостановлен»
            DB::table('sessions')->where('user_id', $target->id)->delete();
        }
    }

    /** Скрыть учителя из каталога или вернуть (поле «Публичный профиль активен»). */
    public function setHidden(User $teacher, bool $hidden): void
    {
        abort_unless($teacher->role === User::ROLE_TUTOR, 404);

        $teacher->forceFill(['is_active' => ! $hidden])->save();
    }

    /** Что удалится вместе с человеком — для окна подтверждения. */
    public function deletionImpact(User $user): array
    {
        return match ($user->role) {
            User::ROLE_TUTOR => [
                'students' => DB::table('teacher_student')->where('teacher_id', $user->id)->count(),
                'lessons' => Room::withTrashed()->where('user_id', $user->id)->count(),
            ],
            User::ROLE_STUDENT => ['teachers' => $user->teachers()->pluck('name')->all()],
            default => [],
        };
    }

    /** Удалить пользователя навсегда (занятия, задания, сообщения — User::deleting). Себя — нельзя. */
    public function delete(User $target, User $admin): void
    {
        abort_if($target->id === $admin->id, 403);

        DB::table('sessions')->where('user_id', $target->id)->delete();
        $target->delete();
    }
}
