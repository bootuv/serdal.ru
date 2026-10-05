<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        // Демо-кабинет — без группы web: ни сессии, ни входа, ни базы (DemoSandbox)
        then: fn () => \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\DemoSandbox::class)
            ->group(base_path('routes/demo.php')),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [\App\Http\Middleware\RedirectTrailingSlash::class]);
        // Выход без проверки токена: сессия живёт SESSION_LIFETIME, а вкладка — дольше; со старым
        // токеном «Выйти из кабинета» вела на «Страница устарела». С чужих сайтов кука сессии
        // не уходит (SameSite=lax), так что выйти «за пользователя» нельзя.
        $middleware->validateCsrfTokens(except: ['logout']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Сервис почты отказал (дневной лимит, сервер недоступен) — извинение вместо «Что-то пошло не так»
        $exceptions->render(fn (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e, \Illuminate\Http\Request $request) => $request->expectsJson()
            ? response()->json(['message' => \App\Support\MailDelivery::FAILED], 503)
            : response()->view('errors.mail', [], 503));
    })->create();
