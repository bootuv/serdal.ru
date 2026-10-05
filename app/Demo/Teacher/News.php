<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * «Новости» учителя — App\Livewire\Cabinet\News. Новости платформы — здесь же (announcements()),
 * их берут NewsItem и баннер на «Сегодня» (App\Demo\NewsBanner).
 * ?read=1 — «Отметить все прочитанными».
 */
class News extends Screen
{
    public const PATH = 'news';

    public const EXAMPLES = ['news', 'news?read=1'];

    /** Непрочитанные новости (важная — ещё и баннером на «Сегодня»). */
    public const UNREAD = [15];

    public string $view = 'livewire.cabinet.news';

    public string $title = 'Новости';

    public ?string $active = 'news';

    public function actions(): array
    {
        return [
            'readAll' => ['set' => ['read' => '1'], 'toast' => 'Все новости отмечены прочитанными'],
        ];
    }

    public function data(): array
    {
        $teacher = auth()->user();
        $unread = $this->state('read', false) ? [] : self::UNREAD;

        // Порядок — как AnnouncementService::visibleFor(): закреплённые, потом новые
        $items = self::announcements()
            ->sortBy(fn (Announcement $a) => [! $a->is_pinned, -$a->published_at->getTimestamp()])
            ->values();

        return [
            'items' => $items->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt(),
                'when' => HumanDate::day($a->published_at),
                'pinned' => $a->is_pinned,
                'unread' => in_array($a->id, $unread, true),
                'url' => AnnouncementService::url($a, $teacher),
            ]),
            'unread' => count($unread),
            'hasMore' => false,
            'limit' => 20,
        ];
    }

    /** @return Collection<int, Announcement> id => новость (в памяти, без базы) */
    public static function announcements(): Collection
    {
        $now = Carbon::now();
        $at = fn (int $days, string $time) => $now->copy()->subDays($days)->setTimeFromTimeString($time);

        $rows = [
            15 => ['Записи занятий — в новом плеере', $at(0, '10:00'), true, false, <<<'HTML'
                <p>Записи занятий теперь открываются в новом плеере — и у вас, и у учеников. Разбор перед контрольной стало удобнее пересматривать.</p>
                <ul>
                    <li>Скорость от 0,5× до 2× — удобно пересматривать разбор перед контрольной, плеер её запоминает;</li>
                    <li>Перемотка на 10 секунд кнопками и стрелками на клавиатуре;</li>
                    <li>Полный экран — доску видно целиком даже с телефона.</li>
                </ul>
                <p>Ничего настраивать не нужно: все записи уже в разделе «Записи», ученики видят записи своих занятий у себя в кабинете.</p>
                HTML],
            14 => ['Перенести занятие можно прямо из расписания', $at(5, '12:30'), false, false, <<<'HTML'
                <p>Откройте занятие в расписании и нажмите «Перенести»: выберите новый день и время — ученики сразу получат уведомление.</p>
                <p>Перенос одного занятия не меняет постоянное расписание. Чтобы сдвинуть все занятия, например с понедельника на вторник, — измените повторение в настройках занятия.</p>
                HTML],
            13 => ['Пригласите коллегу — получите 10 занятий', $at(12, '11:00'), false, true, <<<'HTML'
                <p>Знаете учителя, которому пригодится Serdal? Поделитесь своей ссылкой из раздела «Пригласить коллег».</p>
                <p>Когда приглашённый учитель впервые оплатит тариф, мы начислим вам 10 занятий, а ему — 5. Занятия не сгорают и расходуются после лимита тарифа.</p>
                <p>Приглашать можно сколько угодно коллег: за каждого, кто оплатит тариф, — ещё 10 занятий.</p>
                HTML],
            12 => ['В сообщениях — файлы до 50 МБ и отметки о прочтении', $at(20, '15:00'), false, false, <<<'HTML'
                <p>В чат с учеником или группой теперь можно отправить до 10 файлов сразу, каждый до 50 МБ: сканы, презентации, аудио.</p>
                <p>Две галочки у сообщения значат, что ученик его прочитал. Своё сообщение можно изменить или удалить — оно пропадёт у всех участников чата.</p>
                HTML],
            11 => ['Добро пожаловать в обновлённый кабинет', $at(34, '09:00'), false, false, <<<'HTML'
                <p>Мы полностью обновили кабинет учителя. Главное теперь на экране «Сегодня»: ближайшее занятие, работы на проверку, оплаты и новые сообщения.</p>
                <p>Расписание, ученики, задания и записи остались на своих местах в меню слева. Если что-то не получается — напишите в поддержку, ответим в течение дня.</p>
                HTML],
        ];

        return collect($rows)->map(function (array $r, int $id) {
            $a = new Announcement;
            $a->forceFill([
                'id' => $id,
                'title' => $r[0],
                'published_at' => $r[1],
                'is_important' => $r[2],
                'is_pinned' => $r[3],
                'body' => trim(preg_replace('/\n\s+/', "\n", $r[4])),
                'audience' => Announcement::AUDIENCE_TEACHERS,
            ]);
            $a->exists = true;

            return $a;
        });
    }
}
