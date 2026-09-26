<?php

namespace App\Services;

use App\Mail\TeacherApplicationApproved;
use App\Mail\TeacherApplicationRejected;
use App\Models\TeacherApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Решение по заявке учителя: одобрить (аккаунт учителя + пароль на почту) или отклонить (письмо с причиной).
 * Используется новой админкой (Cabinet\Admin\Applications) и старой (Filament TeacherApplicationResource).
 */
class TeacherApplicationService
{
    /** Пользователь с почтой заявки, если он уже есть (тогда одобрить нельзя). */
    public function existingUser(TeacherApplication $application): ?User
    {
        return User::where('email', $application->email)->first();
    }

    /**
     * Создаёт учителя по заявке: ФИО, контакты, «о себе», классы, предметы, направления, выбранный тариф, пригласивший.
     * Пароль уходит на почту. Если почта уже занята — null, аккаунт не создаётся.
     */
    public function approve(TeacherApplication $application): ?User
    {
        if ($application->status !== TeacherApplication::STATUS_PENDING || $this->existingUser($application)) {
            return null;
        }

        $password = Str::password(10);

        $user = DB::transaction(function () use ($application, $password) {
            $user = User::create([
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'middle_name' => $application->middle_name,
                'email' => $application->email,
                'password' => Hash::make($password),
                'phone' => $application->phone,
                'whatsup' => $application->whatsup,
                'telegram' => $application->telegram,
                'about' => $application->about,
                'role' => User::ROLE_TUTOR,
                'is_active' => true,
                'grade' => $application->grade,
                'is_profile_completed' => false,
                'desired_tariff_id' => $application->desired_tariff_id,
                'referred_by_id' => $application->referred_by_id,
            ]);

            if (! empty($application->subjects)) {
                $user->subjects()->sync($application->subjects);
            }
            if (! empty($application->directs)) {
                $user->directs()->sync($application->directs);
            }

            $application->update([
                'status' => TeacherApplication::STATUS_APPROVED,
                'reject_reason' => null,
                'decided_at' => now(),
            ]);

            return $user;
        });

        Mail::to($user)->send(new TeacherApplicationApproved($user, $password));

        return $user;
    }

    /** Отклоняет заявку; причина (если есть) уходит в письме об отказе. */
    public function reject(TeacherApplication $application, ?string $reason = null): void
    {
        if ($application->status !== TeacherApplication::STATUS_PENDING) {
            return;
        }

        $reason = filled($reason) ? trim($reason) : null;

        $application->update([
            'status' => TeacherApplication::STATUS_REJECTED,
            'reject_reason' => $reason,
            'decided_at' => now(),
        ]);

        Mail::to($application->email)->send(new TeacherApplicationRejected($reason));
    }
}
