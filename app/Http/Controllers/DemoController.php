<?php

namespace App\Http\Controllers;

use App\Demo\Routes;
use App\Demo\Screen;
use App\Demo\Stub;
use App\Demo\World;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * Демо-кабинет учителя для страницы «О платформе» (iframe в ноутбуке) и для показа на весь экран.
 * Настоящие шаблоны экранов + выдуманные данные из App\Demo, без базы (DemoSandbox).
 */
class DemoController extends Controller
{
    /** Сколько секунд браузер держит экран демо (время в демо — «за 12 минут до занятия», пара минут ему не мешает). */
    private const BROWSER_CACHE = 120;

    public function teacher(Request $request, string $path = '')
    {
        $match = Routes::match('teacher', $path);
        if (! $match) {
            // Без страницы 404 сайта: её раскладка читает настройки из базы, а базы в демо нет
            return response('Такого экрана в демо нет', 404)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        [$class, $params] = $match;

        /** @var Screen $screen */
        $screen = new $class($request, $params);

        // Шаблоны и раскладка читают auth()->user(): ставим выдуманного учителя только на этот запрос,
        // без сессии (маршрут без группы web) — ничего не сохраняется и не «логинит» посетителя
        auth()->setUser(World::teacher());
        view()->share('errors', new ViewErrorBag);

        // Вложенные Livewire-компоненты шаблона — подменой (Stub), иначе они полезли бы в базу
        Stub::register($screen->components() + array_fill_keys(Routes::STUBS, null));

        try {
            $data = $screen->data();
            $html = view('demo.page', [
                'screen' => $screen,
                'data' => $data,
                'demo' => $this->layout($screen, $request) + ['models' => self::models($data)],
            ])->render();
        } finally {
            auth()->forgetUser();
        }

        // Данные выдуманные и одинаковые для всех, поэтому браузеру можно держать экран пару минут:
        // demo-cabinet.js скачивает экраны заранее, и переход по клику берёт готовую страницу из кэша
        return response(self::rewrite($html))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, max-age=' . self::BROWSER_CACHE);
    }

    /** Ссылки настоящего кабинета → демо (в том числе экранированные в JSON). */
    public static function rewrite(string $html): string
    {
        $real = rtrim(url('/cabinet/teacher'), '/');
        $demo = rtrim(url('/demo/teacher'), '/');

        $html = str_replace([$real, str_replace('/', '\/', $real)], [$demo, str_replace('/', '\/', $demo)], $html);

        // Запуск и вход в класс (BBB) → экран идущего занятия в демо
        $html = preg_replace('~' . preg_quote(rtrim(url('/'), '/'), '~') . '/rooms/(\d+)/(?:start|connect)~', $demo . '/lessons/$1?class=1', $html);

        // Относительные ссылки: href="/cabinet/teacher…"
        return preg_replace('~(["\'(=\s])/cabinet/teacher(?=[/?"\'\s#]|$)~', '$1/demo/teacher', $html);
    }

    /**
     * Значения полей wire:model: Livewire сам подставляет их в поля, без него это делает demo-cabinet.js.
     * Только простые значения и небольшие массивы — списки строк и т. п. в поля не попадают.
     */
    private static function models(array $data): array
    {
        return array_filter($data, fn ($v) => is_scalar($v) || (is_array($v) && strlen((string) json_encode($v)) < 2000));
    }

    /** Данные для раскладки (x-layouts.cabinet, prop demo) и для demo-cabinet.js. */
    private function layout(Screen $screen, Request $request): array
    {
        return [
            'unread' => 2,
            'messages' => 2,
            'news' => 1,
            'tasks' => 3,
            'reviews' => 1,
            'support' => url('/demo/teacher/messages?support=1'),
            'referrals' => true,
            'referralPromo' => null,
            'notifications' => \App\Demo\Notifications::data(),
            'client' => [
                // Панель уведомлений — первой: пока она открыта, её «закрыть» главнее экранного
                'actions' => (object) (\App\Demo\Notifications::actions() + $screen->actions()),
                'modal' => $screen->modalParams(),
                'props' => (object) $screen->props(),
                'notice' => $screen->notice(),
                'saveText' => 'Это демо — здесь ничего не сохраняется. Зарегистрируйтесь, чтобы попробовать по-настоящему',
                'leaveText' => 'В демо это не открывается — после регистрации всё будет работать',
                'exitUrl' => route('about'),
            ],
        ];
    }
}
