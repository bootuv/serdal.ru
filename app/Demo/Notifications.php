<?php

namespace App\Demo;

use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Панель уведомлений демо-кабинета (колокольчик) — переменные шаблона livewire/cabinet/notifications.blade.php,
 * как App\Livewire\Cabinet\Notifications::render() + публичное свойство $open.
 *
 * Состояние в адресе любого экрана: ?notifications=1 — панель открыта, &notificationsRead=1 — «Прочитать все»,
 * &notificationsCleared=1 — «Очистить список». id уведомления — адрес, куда оно ведёт (visit → переход).
 * Тексты — как у настоящих уведомлений (HomeworkSubmitted, NewMessage, StudentLeftReview, PaymentClaimSubmitted).
 */
final class Notifications
{
    /** Параметры адреса панели: их убирает закрытие. */
    public const PARAMS = ['notifications', 'notificationsRead', 'notificationsCleared'];

    public static function data(): array
    {
        $request = request();
        $open = $request->query('notifications') === '1';
        $read = $request->query('notificationsRead') === '1';
        $items = $open && $request->query('notificationsCleared') !== '1' ? self::items($read) : collect();

        return [
            'open' => $open,
            'fresh' => $items->where('unread', true)->values(),
            'old' => $items->where('unread', false)->values(),
            'unread' => $read ? 0 : 2,
            'settingsUrl' => route('cabinet.teacher.profile', ['tab' => 'notify']),
            'emptyText' => 'Пока пусто — здесь появятся сданные работы, сообщения, отзывы и оплаты.',
        ];
    }

    /**
     * Действия панели для demo-cabinet.js. Пока панель открыта, они главнее действий экрана:
     * её «close» не должен закрывать окна экрана (и наоборот — у «Сообщений» close закрывает чат).
     */
    public static function actions(): array
    {
        if (request()->query('notifications') !== '1') {
            return [];
        }

        $off = array_fill_keys(self::PARAMS, null);

        return [
            'close' => ['set' => $off],
            'visit' => ['go' => '{0}'],
            'readAll' => ['set' => ['notificationsRead' => '1']],
            'clear' => ['set' => ['notificationsCleared' => '1']],
        ];
    }

    private static function items(bool $allRead)
    {
        $now = Carbon::now();
        $ago = fn (int $days, string $time) => $now->copy()->subDays($days)->setTimeFromTimeString($time);
        $name = fn (int $id) => World::student($id)->name;

        $rows = [
            ['Новая работа', $name(101) . ' · «Квадратные уравнения, вариант 3»', 'tasks', $now->copy()->subHours(3), true,
                route('cabinet.teacher.review', ['submission' => 401])],
            ['Новое сообщение в «' . World::ROOMS[202][0] . '»', $name(102) . ': Можно перенести четверг на 18:00?', 'chat', $now->copy()->subHours(2), true,
                route('cabinet.teacher.messages', ['chat' => 102])],
            ['Новая работа', $name(102) . ' · «Производная: задачи 1–12»', 'tasks', $ago(1, '21:15'), false,
                route('cabinet.teacher.review', ['submission' => 402])],
            ['Новый отзыв', $name(104) . ' · оценка 5 из 5 — «Объясняет спокойно и понятно, по геометрии наконец-то появились четвёрки»', 'star', $ago(2, '20:47'), false,
                route('cabinet.teacher.reviews')],
            ['Ученик сообщил об оплате', $name(105) . ': оплачено ' . plural_ru(2, 'занятие', 'занятия', 'занятий') . ' · ' . Money::format(2400) . '. Проверьте чек и подтвердите оплату.', 'wallet', $ago(3, '12:40'), false,
                TeacherStudentsService::studentUrl(World::student(105), ['tab' => 'pay'])],
            ['Новая работа', $name(103) . ' · «Законы Ньютона, задачи 1–8»', 'tasks', $ago(3, '18:02'), false,
                route('cabinet.teacher.review', ['submission' => 403])],
        ];

        // Новые сверху (latest())
        return collect($rows)->sortByDesc(fn (array $r) => $r[3]->getTimestamp())->values()->map(fn (array $r) => [
            'id' => $r[5],
            'title' => $r[0],
            'body' => $r[1],
            'icon' => $r[2],
            'time' => self::time($r[3]),
            'unread' => $r[4] && ! $allRead,
            'link' => true,
        ]);
    }

    /** Как Notifications::time(): «14:05», «вчера», «3 октября». */
    private static function time(CarbonInterface $at): string
    {
        return match (true) {
            $at->isToday() => $at->format('H:i'),
            $at->isYesterday() => 'вчера',
            default => HumanDate::date($at),
        };
    }
}
