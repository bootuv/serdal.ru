<?php

/*
 * Кабинеты учителя, ученика и админка (Livewire + Blade, docs/design/BRAND.md).
 * Адреса старых Filament-панелей (/tutor, /student, /admin) переадресует маршрут в конце файла.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\CheckUserActive::class])
    ->prefix('cabinet')
    ->name('cabinet.')
    ->group(function () {
        // /cabinet — открывает кабинет своей роли
        Route::get('/', fn () => redirect(\App\Http\Middleware\EnsureCabinetRole::homeFor(auth()->user())))->name('home');

        Route::get('/student', \App\Livewire\Cabinet\Student\Home::class)->name('student.home')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':student');

        // Почта и пароль — один экран на все роли, смена подтверждается кодом из письма
        Route::get('/account', \App\Livewire\Cabinet\Account::class)->name('account');

        // Сообщения — один экран на обе роли
        Route::get('/student/messages', \App\Livewire\Cabinet\Messages::class)->name('student.messages')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':student');
        Route::get('/teacher/messages', \App\Livewire\Cabinet\Messages::class)->name('teacher.messages')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':teacher');

        // Новости от администрации — общие экраны для обеих ролей
        Route::get('/student/news', \App\Livewire\Cabinet\News::class)->name('student.news')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':student');
        Route::get('/student/news/{announcement}', \App\Livewire\Cabinet\NewsItem::class)->name('student.news-item')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':student');
        Route::get('/teacher/news', \App\Livewire\Cabinet\News::class)->name('teacher.news')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':teacher');
        Route::get('/teacher/news/{announcement}', \App\Livewire\Cabinet\NewsItem::class)->name('teacher.news-item')->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':teacher');

        // Экраны ученика: маршрут подключается, когда готов класс экрана (до этого меню ведёт в старый кабинет)
        $student = [
            'schedule' => ['/student/schedule', 'Schedule'],
            'tasks' => ['/student/tasks', 'Tasks'],
            'task' => ['/student/tasks/{homework}', 'Task'],
            'lesson' => ['/student/lessons/{room}', 'Lesson'],
            'materials' => ['/student/materials', 'Materials'],
            'recordings' => ['/student/recordings', 'Recordings'],
            'payments' => ['/student/payments', 'Payments'],
            'profile' => ['/student/profile', 'Profile'],
        ];
        foreach ($student as $name => [$uri, $class]) {
            $fqcn = 'App\\Livewire\\Cabinet\\Student\\' . $class;
            if (class_exists($fqcn)) {
                Route::get($uri, $fqcn)->name('student.' . $name)->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':student');
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
            'task' => ['/teacher/tasks/{homework}', 'Task'],
            'review' => ['/teacher/tasks/review/{submission}', 'Review'],
            'materials' => ['/teacher/materials', 'Materials'],
            'recordings' => ['/teacher/recordings', 'Recordings'],
            'reviews' => ['/teacher/reviews', 'Reviews'],
            'profile' => ['/teacher/profile', 'Profile'],
            'subscription' => ['/teacher/subscription', 'Subscription'],
            'payments' => ['/teacher/payments', 'Payments'],
            'referrals' => ['/teacher/referrals', 'Referrals'],
            'onboarding' => ['/teacher/onboarding', 'Onboarding'],
        ];
        foreach ($teacher as $name => [$uri, $class]) {
            $fqcn = 'App\\Livewire\\Cabinet\\Teacher\\' . $class;
            if (class_exists($fqcn)) {
                Route::get($uri, $fqcn)->name('teacher.' . $name)->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':teacher');
            }
        }

        // Админка. Маршрут подключается, когда готов класс экрана.
        $admin = [
            'today' => ['/admin', 'Today'],
            'support' => ['/admin/support', 'Support'],
            'applications' => ['/admin/applications', 'Applications'],
            'reviews' => ['/admin/reviews', 'Reviews'],
            'lessons' => ['/admin/lessons', 'Lessons'],           // вкладки: расписание, проведённые, запросы на удаление, записи (?tab=)
            'lesson' => ['/admin/lessons/{room}', 'Lesson'],
            'session' => ['/admin/sessions/{session}', 'Session'],
            'users' => ['/admin/users', 'Users'],
            'user' => ['/admin/users/{user}', 'User'],
            'payments' => ['/admin/payments', 'Payments'],        // вкладки: платежи, подписки
            'tariffs' => ['/admin/tariffs', 'Tariffs'],
            'tariff' => ['/admin/tariffs/{tariff}', 'Tariff'],     // {tariff} = id или new
            'referrals' => ['/admin/referrals', 'Referrals'],
            'founders' => ['/admin/founders', 'Founders'],        // вкладки: взносы, расходы, доли (?tab=)
            'news' => ['/admin/news', 'News'],                    // вкладки: опубликованные, запланированные, черновики (?tab=)
            'news-item' => ['/admin/news/{announcement}', 'NewsItem'], // {announcement} = id или new
            'blog' => ['/admin/blog', 'Blog'],                      // вкладки: опубликованные, запланированные, черновики (?tab=)
            'blog-article' => ['/admin/blog/{post}', 'BlogArticle'], // {post} = id или new
            'mailings' => ['/admin/mailings', 'Mailings'],          // вкладки: письма, списки (?tab=)
            'mailing' => ['/admin/mailings/{campaign}', 'Mailing'],  // {campaign} = id или new
            'mailing-list' => ['/admin/mailings/lists/{list}', 'MailingList'],
            'help' => ['/admin/help', 'Help'],
            'help-article' => ['/admin/help/articles/{article}', 'HelpArticle'], // {article} = id или new
            'settings' => ['/admin/settings', 'Settings'],        // вкладки, в т.ч. «Справочники» (предметы и направления)
        ];
        foreach ($admin as $name => [$uri, $class]) {
            $fqcn = 'App\\Livewire\\Cabinet\\Admin\\' . $class;
            if (class_exists($fqcn)) {
                Route::get($uri, $fqcn)->name('admin.' . $name)->middleware(\App\Http\Middleware\EnsureCabinetRole::class . ':admin');
            }
        }
    });

// Старые Filament-панели (/tutor, /student, /admin) удалены: закладки и ссылки из старых писем
// ведут на тот же экран нового кабинета (CabinetUrl), иначе — на главную кабинета своей роли. Гость — на вход.
Route::get('/{panel}/{path?}', function (\Illuminate\Http\Request $request, string $panel, ?string $path = null) {
    $user = $request->user();
    if (! $user) {
        return str_starts_with((string) $path, 'password-reset')
            ? redirect()->route('password.request')
            : redirect()->guest(route('login'));
    }
    $url = $request->fullUrl();
    $target = \App\Support\CabinetUrl::fromLegacy($url, $user);

    return redirect($target && $target !== $url ? $target : \App\Http\Middleware\EnsureCabinetRole::homeFor($user));
})->where(['panel' => 'tutor|student|admin', 'path' => '.*'])
    ->middleware(\App\Http\Middleware\CheckUserActive::class)
    ->name('legacy.cabinet');
