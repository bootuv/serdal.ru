<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;

class ReferralController extends Controller
{
    /**
     * Ссылка-приглашение /r/{code}: запоминаем пригласившего в cookie
     * и ведём на страницу заявки преподавателя.
     */
    public function invite(string $code): RedirectResponse
    {
        $redirect = redirect()->route('become-tutor');

        if (!ReferralService::enabled() || !ReferralService::findReferrer($code)) {
            return $redirect;
        }

        return $redirect->withCookie(cookie(
            ReferralService::COOKIE,
            strtolower($code),
            ReferralService::cookieDays() * 24 * 60,
        ));
    }
}
