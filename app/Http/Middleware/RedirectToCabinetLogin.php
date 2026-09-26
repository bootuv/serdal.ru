<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Страницы входа и «Забыли пароль?» панелей Filament (/admin/login, /tutor/login, /student/login, /login/login)
 * ведут на общий вход /login. Ссылки сброса пароля из уже отправленных писем Filament продолжают работать.
 */
class RedirectToCabinetLogin
{
    public function handle(Request $request, Closure $next)
    {
        $route = (string) $request->route()?->getName();

        if ($request->isMethod('GET') && str_ends_with($route, '.auth.login')) {
            return redirect()->route('login');
        }
        if ($request->isMethod('GET') && str_ends_with($route, '.auth.password-reset.request')) {
            return redirect()->route('password.request');
        }

        return $next($request);
    }
}
