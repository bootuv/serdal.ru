<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class RedirectIfNotAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // Skip redirect for login and logout pages
        if ($request->is('admin/login') || $request->is('admin/logout') || $request->is('admin/password-reset/*')) {
            return $next($request);
        }

        // If user is authenticated but not admin, redirect to their panel
        if (auth()->check() && auth()->user()->role !== User::ROLE_ADMIN) {
            $url = match (auth()->user()->role) {
                User::ROLE_STUDENT => '/student',
                default => '/tutor',
            };

            return redirect($url);
        }

        return $next($request);
    }
}
