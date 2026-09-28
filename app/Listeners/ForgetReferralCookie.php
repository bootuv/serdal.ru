<?php

namespace App\Listeners;

use App\Services\ReferralService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;

/**
 * Вход в аккаунт стирает ссылку-приглашение из браузера: здесь работает уже зарегистрированный
 * пользователь (часто сам пригласивший проверял свою ссылку), и следующая заявка с этого
 * браузера не должна засчитываться как приглашённая.
 */
class ForgetReferralCookie
{
    public function handle(Login $event): void
    {
        if (request()->hasCookie(ReferralService::COOKIE)) {
            Cookie::queue(Cookie::forget(ReferralService::COOKIE));
        }
    }
}
