<?php

namespace App\Http\Middleware;

use App\Support\CabinetUrl;
use Closure;
use Illuminate\Http\Request;

/**
 * Старые кабинеты Filament (/tutor, /student) закрыты: любая страница ведёт в новый кабинет —
 * на тот же экран, если он есть (CabinetUrl), иначе на главную кабинета своей роли.
 * Не трогаем: запросы Livewire и формы (POST — например, выход), вход и ссылки сброса пароля из старых писем.
 */
class RedirectOldCabinet
{
    public function handle(Request $request, Closure $next)
    {
        $route = (string) $request->route()?->getName();

        if (! $request->isMethod('GET') || $request->hasHeader('X-Livewire')
            || str_contains($route, '.auth.')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }

        $url = $request->fullUrl();
        $target = CabinetUrl::fromLegacy($url, $user);

        return redirect($target && $target !== $url ? $target : EnsureCabinetRole::homeFor($user));
    }
}
