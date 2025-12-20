<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Если пользователь авторизован, роль tutor и профиль не заполнен
        if ($user && $user->role === 'tutor' && !$user->is_profile_completed) {

            $onboardingRoute = 'filament.app.pages.onboarding';
            $logoutRoute = 'filament.app.auth.logout';
            $currentRoute = $request->route()?->getName();

            // Если мы уже на странице онбординга или выходим - пропускаем
            if ($currentRoute === $onboardingRoute || $currentRoute === $logoutRoute) {
                return $next($request);
            }

            // Разрешаем Livewire апдейты (иначе форма не будет работать)
            // Livewire маршруты могут быть 'livewire.update' или содержать livewire в пути
            if ($request->routeIs('livewire.*') || str_contains($request->path(), 'livewire/')) {
                return $next($request);
            }

            // Иначе редирект на онбординг
            return redirect()->route($onboardingRoute);
        }

        return $next($request);
    }
}
