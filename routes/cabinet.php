<?php

/*
 * Новые кабинеты учителя и ученика (Livewire + Blade, docs/design/BRAND.md).
 * Работают параллельно со старыми Filament-панелями /student и /tutor; экраны переключаем по одному.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\CheckUserActive::class])
    ->prefix('cabinet')
    ->name('cabinet.')
    ->group(function () {
        Route::get('/student', \App\Livewire\Cabinet\Student\Home::class)->name('student.home');

        // Экраны ученика: маршрут подключается, когда готов класс экрана (до этого меню ведёт в старый кабинет)
        $student = [
            'schedule' => ['/student/schedule', 'Schedule'],
            'tasks' => ['/student/tasks', 'Tasks'],
            'task' => ['/student/tasks/{homework}', 'Task'],
            'materials' => ['/student/materials', 'Materials'],
            'recordings' => ['/student/recordings', 'Recordings'],
            'payments' => ['/student/payments', 'Payments'],
            'profile' => ['/student/profile', 'Profile'],
        ];
        foreach ($student as $name => [$uri, $class]) {
            $fqcn = 'App\\Livewire\\Cabinet\\Student\\' . $class;
            if (class_exists($fqcn)) {
                Route::get($uri, $fqcn)->name('student.' . $name);
            }
        }

        // Экраны учителя: маршрут подключается, когда готов класс экрана
        $teacher = [
            'today' => ['/teacher', 'Today'],
            'schedule' => ['/teacher/schedule', 'Schedule'],
            'lesson' => ['/teacher/lessons/{room}', 'Lesson'],
            'students' => ['/teacher/students', 'Students'],
            'student' => ['/teacher/students/{student}', 'Student'],
            'tasks' => ['/teacher/tasks', 'Tasks'],
            'task-new' => ['/teacher/tasks/new', 'TaskNew'],
            'review' => ['/teacher/tasks/review/{submission}', 'Review'],
            'materials' => ['/teacher/materials', 'Materials'],
            'recordings' => ['/teacher/recordings', 'Recordings'],
            'reviews' => ['/teacher/reviews', 'Reviews'],
            'profile' => ['/teacher/profile', 'Profile'],
            'subscription' => ['/teacher/subscription', 'Subscription'],
            'referrals' => ['/teacher/referrals', 'Referrals'],
            'onboarding' => ['/teacher/onboarding', 'Onboarding'],
        ];
        foreach ($teacher as $name => [$uri, $class]) {
            $fqcn = 'App\\Livewire\\Cabinet\\Teacher\\' . $class;
            if (class_exists($fqcn)) {
                Route::get($uri, $fqcn)->name('teacher.' . $name);
            }
        }
    });
