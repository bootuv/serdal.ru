<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;

class ReferralController extends Controller
{
    /**
     * Ссылка-приглашение /r/{code}: запоминаем пригласившего в cookie
     * и ведём на страницу заявки преподавателя.
     * Вошедший пользователь (чаще всего сам пригласивший проверяет ссылку) cookie не получает:
     * иначе приглашение досталось бы следующему, кто подаст заявку с этого браузера.
     */
    public function invite(string $code): RedirectResponse
    {
        $redirect = redirect()->route('become-tutor');

        if (auth()->check() || !ReferralService::enabled() || !ReferralService::findReferrer($code)) {
            return $redirect;
        }

        return $redirect->withCookie(cookie(
            ReferralService::COOKIE,
            strtolower($code),
            ReferralService::cookieDays() * 24 * 60,
        ));
    }
}
