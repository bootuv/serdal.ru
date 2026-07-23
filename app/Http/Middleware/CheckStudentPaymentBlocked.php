<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckStudentPaymentBlocked
{
    /**
     * Ученик с блокировкой за неоплату видит только страницу «Оплата».
     * Блокировка снимается автоматически, когда учитель отмечает оплату.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->role === 'student' && $user->payment_blocked_at) {

            $paymentRoute = 'filament.student.pages.payment-debts';
            $logoutRoute = 'filament.student.auth.logout';
            $currentRoute = $request->route()?->getName();

            // Страница оплаты и выход доступны всегда
            if ($currentRoute === $paymentRoute || $currentRoute === $logoutRoute) {
                return $next($request);
            }

            // Разрешаем Livewire-апдейты, иначе страница оплаты не будет работать
            if ($request->routeIs('livewire.*') || str_contains($request->path(), 'livewire/')) {
                return $next($request);
            }

            return redirect()->route($paymentRoute);
        }

        return $next($request);
    }
}
