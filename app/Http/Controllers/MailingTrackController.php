<?php

namespace App\Http\Controllers;

use App\Services\MailingService;
use Illuminate\Http\Request;

/**
 * Ссылки из писем рассылки (App\Services\MailingService): картинка учёта открытий, переход по ссылке, отписка.
 * Отписка по ссылке — через страницу с кнопкой: почтовые сканеры открывают ссылки сами и не должны отписывать адрес.
 * POST без страницы — «отписка в один клик» из почтовой программы (заголовок List-Unsubscribe-Post).
 */
class MailingTrackController extends Controller
{
    /** Прозрачный GIF 1×1. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private MailingService $mailings) {}

    public function open(string $token)
    {
        $this->mailings->trackOpen($token);

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(string $token, int $link)
    {
        $url = $this->mailings->trackClick($token, $link);

        return $url ? redirect()->away($url) : redirect('/');
    }

    public function unsubscribePage(string $token)
    {
        if ($token === 'test') {
            return view('mailing.unsubscribe', ['state' => 'test']);
        }

        $delivery = $this->mailings->delivery($token);

        return view('mailing.unsubscribe', [
            'state' => match (true) {
                ! $delivery => 'missing',
                (bool) ($delivery->contact?->unsubscribed_at ?? $delivery->unsubscribed_at) => 'done',
                default => 'confirm',
            },
            'email' => $delivery?->email,
            'token' => $token,
        ]);
    }

    public function unsubscribe(Request $request, string $token)
    {
        $delivery = $token === 'test' ? null : $this->mailings->delivery($token);
        if ($delivery) {
            $this->mailings->unsubscribe($delivery);
        }

        // Почтовая программа (один клик) ждёт простой ответ, человеку — страница
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', $delivery ? 200 : 404);
        }

        return view('mailing.unsubscribe', ['state' => $delivery ? 'done' : ($token === 'test' ? 'test' : 'missing'), 'email' => $delivery?->email, 'token' => $token]);
    }
}
