<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /about/ → /about (301): у каждой страницы один адрес, поисковики не видят дублей.
 * Только для GET/HEAD — формы и запросы Livewire не трогаем.
 */
class RedirectTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        if ($path !== '/' && str_ends_with($path, '/') && $request->isMethodSafe()) {
            $query = $request->getQueryString();

            return redirect()->to(rtrim($path, '/') . ($query !== null ? '?' . $query : ''), 301);
        }

        return $next($request);
    }
}
