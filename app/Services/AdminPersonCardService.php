<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * «Карточка человека» в админке (поддержка): контакты и коротко о человеке на Serdal.
 * Оплату занятий между учеником и учителем администратор не видит — здесь её нет. Платёж за тариф — видит.
 */
class AdminPersonCardService
{
    /** Подпись под именем: «Учитель · история, обществознание», «Ученик · учитель Мария Соколова». */
    public function subline(User $user): string
    {
        $role = MessengerService::roleLabel($user);

        if ($user->role === User::ROLE_TUTOR) {
            $subjects = mb_strtolower($user->subjects()->pluck('name')->implode(', '));

            return $subjects !== '' ? $role . ' · ' . $subjects : $role;
        }

        if ($user->role === User::ROLE_STUDENT) {
            $teachers = $user->teachers()->pluck('name')->all();

            // Имена не склоняем: «учитель Мария Соколова», «учителя Анна Белова, Мария Соколова»
            return $teachers ? $role . ' · ' . (count($teachers) > 1 ? 'учителя ' : 'учитель ') . implode(', ', $teachers) : $role;
        }

        return $role;
    }

    /**
     * @return array{name:string, role:string, email:?string, phone:?string, telegram:?string, whatsapp:?string,
     *               links:array, rows:array<int, array{k:string, v:string, note:?string, em:?string}>, url:?string}
     */
    public function card(User $user): array
    {
        $rows = match ($user->role) {
            User::ROLE_TUTOR => $this->teacherRows($user),
            User::ROLE_STUDENT => $this->studentRows($user),
            default => [],
        };
        $rows[] = ['k' => 'Регистрация', 'v' => HumanDate::date($user->created_at ?? now()), 'note' => null, 'em' => null];

        return [
            'name' => $user->name,
            'role' => MessengerService::roleLabel($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'telegram' => $user->telegram ? '@' . ltrim($user->telegram, '@') : null,
            'telegramUrl' => $user->telegram ? 'https://t.me/' . ltrim($user->telegram, '@') : null,
            'whatsapp' => $user->whatsup,
            'whatsappUrl' => $user->whatsup ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $user->whatsup) : null,
            'rows' => $rows,
            'url' => Route::has('cabinet.admin.user') ? route('cabinet.admin.user', $user->id) : null,
        ];
    }

    private function teacherRows(User $user): array
    {
        $rows = [];
        if (($subjects = $user->subjects()->pluck('name'))->isNotEmpty()) {
            $rows[] = ['k' => 'Предметы', 'v' => \Illuminate\Support\Str::ucfirst(mb_strtolower($subjects->implode(', '))), 'note' => null, 'em' => null];
        }
        if ($grade = $user->display_grade) {
            $rows[] = ['k' => 'Классы', 'v' => $grade, 'note' => null, 'em' => null];
        }

        $subscription = $user->activeSubscription();
        $tariff = $subscription?->tariff
            ? '«' . $subscription->tariff->name . '»' . ($subscription->ends_at ? ' до ' . HumanDate::date($subscription->ends_at) : '')
            : 'Без тарифа';
        // Исключение: платёж за тариф, который ждёт оплаты дольше суток
        $pending = SubscriptionPayment::where('user_id', $user->id)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->where('created_at', '<', now()->subDay())
            ->with('tariff')
            ->latest('id')
            ->first();
        $rows[] = [
            'k' => 'Тариф',
            'v' => $tariff,
            'note' => $pending ? 'Платёж ' . Money::format((int) $pending->amount) . ($pending->tariff && ! $pending->isExtraLessons() ? ' за «' . $pending->tariff->name . '»' : '') : null,
            'em' => $pending ? 'ждёт оплаты с ' . HumanDate::day($pending->created_at) : null,
        ];

        $students = DB::table('teacher_student')->where('teacher_id', $user->id)->count();
        $rows[] = ['k' => 'Учеников', 'v' => (string) $students, 'note' => null, 'em' => null];

        return $rows;
    }

    private function studentRows(User $user): array
    {
        $rows = [];
        $teachers = $user->teachers()->with('subjects:id,name')->get();
        if ($teachers->isNotEmpty()) {
            $subjects = $teachers->flatMap(fn (User $t) => $t->subjects->pluck('name'))->map(fn ($n) => mb_strtolower($n))->unique()->implode(', ');
            $rows[] = ['k' => $teachers->count() > 1 ? 'Учителя' : 'Учитель', 'v' => $teachers->pluck('name')->implode(', '), 'note' => $subjects ?: null, 'em' => null];
        }

        $grade = StudentProfileService::gradeForForm($user->grade);
        if ($grade !== null && isset(StudentProfileService::GRADES[$grade])) {
            $rows[] = ['k' => 'Класс', 'v' => StudentProfileService::GRADES[$grade], 'note' => null, 'em' => null];
        }

        return $rows;
    }
}
