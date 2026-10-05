<?php

namespace App\Http\Middleware;

use App\Demo\World;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Изоляция демо-кабинета (/demo/…): у запроса нет базы, сессии, очередей и почты.
 *
 * База подменяется соединением на несуществующий файл: если шаблон или сервис случайно
 * полезет в базу, будет ошибка, а не настоящие данные на публичной странице.
 * Время «замораживается» (World::now), чтобы кабинет выглядел живым в любой час.
 * Маршруты демо подключены без группы web (routes/demo.php): ни куки сессии, ни входа.
 */
class DemoSandbox
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'database.connections.demo_none' => [
                'driver' => 'sqlite',
                'database' => storage_path('framework/demo-has-no-database.sqlite'),
                'prefix' => '',
            ],
            'database.default' => 'demo_none',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'broadcasting.default' => 'null',
            'livewire.inject_assets' => false,
        ]);
        DB::purge();

        $now = World::now();
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        try {
            return $next($request);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }
}
