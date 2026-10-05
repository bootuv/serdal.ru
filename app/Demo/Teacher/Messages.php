<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Demo\World;
use App\Models\User;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * «Сообщения» учителя — App\Livewire\Cabinet\Messages (общий экран учителя и ученика), данные — как MessengerService::dialogs() / thread().
 *
 * Состояние в адресе: ?chat=101 — чат с учеником (id из World), ?chat=206 — чат группы, ?chat=support или ?support=1 — поддержка;
 * ?q= — поиск, ?editing= / ?deleting= — изменение и удаление сообщения, ?sent= — только что отправленное сообщение
 * (дорисовывается в конец переписки; текст подставляет demo-cabinet.js из поля, см. actions()).
 */
class Messages extends Screen
{
    public const PATH = 'messages';

    /** Состояния для DemoCabinetTest. */
    public const EXAMPLES = [
        'messages',
        'messages?chat=101',
        'messages?chat=206',
        'messages?chat=104',
        'messages?support=1',
        'messages?chat=191',
        'messages?chat=107&sent=Аминат, жду на занятии в среду',
        'messages?chat=101&editing=10104',
        'messages?chat=101&deleting=10105',
        'messages?q=Адам',
    ];

    /** Бывший ученик — архивный личный чат (в World его нет: занятия закончились). */
    private const ARCHIVED = 191;

    public string $view = 'livewire.cabinet.messages';

    public string $title = 'Сообщения';

    public ?string $active = 'messages';

    public bool $bare = true;

    public function actions(): array
    {
        $reset = ['support' => null, 'editing' => null, 'deleting' => null, 'sent' => null];

        return [
            'open' => ['set' => ['chat' => '{0}'] + $reset],
            'close' => ['set' => ['chat' => null] + $reset],
            // {@draft} — значение поля wire:model="draft" (нужна поддержка в demo-cabinet.js; без неё — только тост)
            'send' => ['set' => ['sent' => '{@draft}'], 'toast' => 'Это демо — сообщение видно только вам'],
            'edit' => ['set' => ['editing' => '{0}', 'sent' => null]],
            'cancelEdit' => ['set' => ['editing' => null]],
            'confirmDelete' => ['set' => ['deleting' => '{0}']],
            'cancelDelete' => ['set' => ['deleting' => null]],
            'delete' => ['set' => ['deleting' => null], 'toast' => 'Это демо — сообщение останется на месте'],
        ];
    }

    public function modalParams(): array
    {
        return ['deleting', 'editing'];
    }

    public function data(): array
    {
        $chats = $this->chats();
        $openKey = $this->openKey();

        $sent = $this->sentText();
        $editingId = $this->state('editing', 0) ?: null;
        $draft = '';

        // Отправленное (или изменённое) сообщение дорисовываем в открытый чат
        if ($sent !== null && isset($chats[$openKey])) {
            if ($editingId) {
                $chats[$openKey]['messages'] = array_map(
                    fn (array $m) => $m['id'] === $editingId ? ['text' => $sent] + $m : $m,
                    $chats[$openKey]['messages'],
                );
                $editingId = null;
            } else {
                $chats[$openKey]['messages'][] = ['id' => 99901, 'from' => World::TEACHER_ID, 'text' => $sent, 'at' => Carbon::now(), 'files' => [], 'read' => false];
            }
        } elseif ($editingId && isset($chats[$openKey])) {
            $draft = collect($chats[$openKey]['messages'])->firstWhere('id', $editingId)['text'] ?? '';
        }

        $q = $this->state('q', '');
        $needle = mb_strtolower(trim($q));
        $match = fn (array $d) => $needle === '' || str_contains(mb_strtolower($d['name']), $needle);

        $list = collect($chats)->map(fn (array $c, string $key) => $this->dialog($key, $c, $key === $openKey));
        $support = $list->pull('support');
        $dialogs = $list->sortByDesc('sort')->values();
        $current = $openKey ? ($openKey === 'support' ? $support : $dialogs->firstWhere('key', $openKey)) : null;

        return [
            'supportDialog' => $match($support) ? $support : null,
            'dialogs' => $dialogs->filter($match)->values(),
            'current' => $current,
            'thread' => $current ? $this->thread($chats[$openKey]) : null,
            'canWrite' => $current && ! $current['archived'],
            // Публичные свойства компонента
            'room' => $current && $current['room_id'] ? $current['room_id'] : null,
            'personal' => $current && ($current['personal_id'] ?? null) ? $current['personal_id'] : null,
            'with' => null,
            'support' => $openKey === 'support',
            'q' => $q,
            'draft' => $draft,
            'limit' => 30,
            'picked' => [],
            'files' => [],
            'editingId' => $editingId,
            'deletingId' => $this->state('deleting', 0) ?: null,
        ];
    }

    /** Открытый чат: id ученика / группы, «support» или null. */
    private function openKey(): ?string
    {
        if ($this->state('support', false)) {
            return 'support';
        }
        $chat = $this->state('chat', '');

        return array_key_exists($chat, $this->chats()) ? $chat : null;
    }

    /** Текст из ?sent= (пустой или неподставленный шаблон — нет сообщения). */
    private function sentText(): ?string
    {
        $text = trim(mb_substr($this->state('sent', ''), 0, 5000));

        return $text === '' || str_starts_with($text, '{') ? null : $text;
    }

    /* ---------- Строки списка и ленты — как MessengerService::dialogs(), describe(), thread() ---------- */

    private function dialog(string $key, array $c, bool $open): array
    {
        $last = end($c['messages']);
        $teacher = World::TEACHER_ID;
        $body = trim($last['text']) !== '' ? $last['text'] : ($last['files'][0]['name'] ?? 'Файл');
        $body = str_replace(["\r", "\n"], ' ', $body);
        $preview = match (true) {
            $last['from'] === $teacher => 'Вы: ' . $body,
            $c['type'] === 'group' => $this->author($last['from'])->name . ': ' . $body,
            default => $body,
        };
        // Открытый чат прочитан (markRead при открытии)
        $unread = $open ? 0 : collect($c['messages'])->where('from', '!=', $teacher)->where('unread', true)->count();

        return [
            'key' => $key,
            'type' => $c['type'],
            'name' => $c['name'],
            'sub' => $c['sub'],
            'link' => $c['link'],
            'archived' => $c['archived'] ?? false,
            'avatar' => $c['avatar'],
            'room_id' => $c['room'] ?? null,
            'personal_id' => $c['personal'] ?? null,
            'unread' => $unread,
            'preview' => $preview,
            'time' => $this->shortTime($last['at']),
            'sort' => $last['at']->getTimestamp(),
        ];
    }

    private function thread(array $c): array
    {
        $teacher = World::TEACHER_ID;
        $support = $c['type'] === 'support';
        $items = [];
        $day = null;

        foreach ($c['messages'] as $m) {
            $d = $m['at']->toDateString();
            if ($d !== $day) {
                $day = $d;
                $label = HumanDate::day($m['at']);
                $items[] = ['day' => mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1)];
            }
            $own = $m['from'] === $teacher;
            $items[] = [
                'id' => $m['id'],
                'own' => $own,
                'who' => match (true) {
                    $own => null,
                    $support => 'Поддержка Serdal',
                    $c['type'] === 'group' => $this->author($m['from'])->name,
                    default => null,
                },
                'text' => $m['text'],
                'files' => $m['files'],
                'time' => $m['at']->format('H:i'),
                'read' => $own && ($m['read'] ?? true),
                'canEdit' => $own && trim($m['text']) !== '',
                // Учитель удаляет любое сообщение в своём занятии и личном чате; в поддержке — только свои
                'canDelete' => $own || ! $support,
            ];
        }

        return ['items' => $items, 'more' => false];
    }

    /** «14:05», «вчера», «вт», «28 мая» — как MessengerService::shortTime(). */
    private function shortTime(Carbon $at): string
    {
        return match (true) {
            $at->isToday() => $at->format('H:i'),
            $at->isYesterday() => 'вчера',
            $at->greaterThan(now()->subDays(6)->startOfDay()) => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$at->dayOfWeek],
            default => HumanDate::date($at),
        };
    }

    private function author(int $id): User
    {
        return $id === self::ARCHIVED ? self::archivedStudent() : World::student($id);
    }

    private static function archivedStudent(): User
    {
        $user = new User;
        $user->forceFill([
            'id' => self::ARCHIVED,
            'name' => 'Барахоев Ахмед',
            'last_name' => 'Барахоев',
            'first_name' => 'Ахмед',
            'role' => User::ROLE_STUDENT,
            'avatar' => null,
            'email' => 'akhmed.barakhoev@example.com',
        ]);
        $user->exists = true;

        return $user;
    }

    /* ---------- Переписки ---------- */

    /**
     * Чаты: ключ (как ?chat=) => описание и сообщения [id, from, text, at, files, unread?, read?].
     * Индивидуальные ученики — чаты их занятий (как в настоящем кабинете), Аминат — личный чат.
     */
    private function chats(): array
    {
        $now = Carbon::now();
        $ago = fn (int $days, string $time) => $now->copy()->subDays($days)->setTimeFromTimeString($time);
        $me = World::TEACHER_ID;

        $chats = [
            'support' => [
                'type' => 'support', 'name' => 'Поддержка Serdal', 'sub' => 'Вопросы о кабинете, оплате и занятиях', 'link' => null, 'avatar' => null,
                'messages' => [
                    [$me, 'Здравствуйте! Подскажите, сколько хранятся записи занятий? Хочу, чтобы ученики могли пересматривать разборы перед экзаменом.', $ago(9, '10:12')],
                    [0, 'Здравствуйте, Зарема! На тарифе «Профи» записи хранятся 90 дней, потом удаляются автоматически. Ученики видят записи своих занятий у себя в кабинете — отдельно ничего отправлять не нужно.', $ago(9, '10:31')],
                    [$me, 'Поняла, спасибо!', $ago(9, '10:33')],
                ],
            ],
            '101' => $this->roomChat(101, [
                [$me, 'Рустам, на пятницу — вариант 3, задания 1–12. Файл прикрепила, решай в тетради и присылай фото.', $ago(3, '17:10'), [self::file('Квадратные уравнения, вариант 3.pdf', 253952)]],
                [101, 'Хорошо, сделаю', $ago(3, '17:24')],
                [101, 'Здравствуйте! А в 9-м дискриминант отрицательный получается, так и должно быть?', $ago(1, '19:40')],
                [$me, 'Да, значит корней нет — так и запиши в ответ.', $ago(1, '19:52')],
                [101, '', $now->copy()->subMinutes(45), [self::image('Решение, задания 1–6.jpg')]],
                [101, 'Отправил домашку, в пятом не уверен — посмотрите?', $now->copy()->subMinutes(40), [], true],
            ]),
            '102' => $this->roomChat(102, [
                [$me, 'Мадина, сегодня хорошо разобрали параметры. На дом — задачи 1–10 из файла, 7-ю и 9-ю со звёздочкой можно оставить на занятие.', $ago(2, '19:05'), [self::file('Параметры, задачи 1–10.pdf', 1153434)]],
                [102, 'Спасибо! 7-я вообще не получается, разберём на следующем?', $ago(2, '19:30')],
                [$me, 'Конечно, начнём с неё.', $ago(2, '19:32')],
                [102, 'Можно перенести четверг на 18:00?', $now->copy()->subHours(2), [], true],
            ]),
            '103' => $this->roomChat(103, [
                [$me, 'Адам, к следующему занятию повтори второй закон Ньютона и проекции сил на наклонной плоскости.', $ago(5, '20:40')],
                [103, 'Хорошо. А задачи из сборника тоже делать?', $ago(5, '21:10')],
                [$me, 'Да, 152–158. Остальное решим вместе.', $ago(5, '21:12')],
                [103, 'Сдал задачи в заданиях, 6-ю не понял совсем', $ago(3, '18:05')],
                [$me, 'Ничего страшного, разберём её первой.', $ago(3, '18:20')],
            ]),
            '104' => $this->roomChat(104, [
                [104, 'Здравствуйте! Это мама Лейлы. Подскажите, как у неё успехи по геометрии? Говорит, что всё понимает, а за контрольную в школе получила тройку.', $ago(1, '21:03')],
                [$me, "Здравствуйте! Лейла старается, теоремы знает хорошо. Проседает оформление решений и задачи на доказательство — над этим сейчас и работаем.\nЧерез две недели напишем пробную контрольную, пришлю результаты.", $ago(0, '09:15')],
                [104, 'Спасибо большое, будем ждать!', $ago(0, '09:40')],
            ]),
            '105' => $this->roomChat(105, [
                [$me, 'Хава, отлично поработала сегодня! Карточка с формулами сокращённого умножения — в файле, повесь над столом.', $ago(6, '12:10'), [self::file('Формулы сокращённого умножения.pdf', 98304)]],
                [105, 'Спасибо!! Уже распечатала', $ago(6, '12:31')],
            ]),
            '206' => $this->groupChat([
                [$me, 'Всем добрый вечер! В следующий раз пишем пробник целиком, как на экзамене. Возьмите черновики и линейку.', $ago(4, '20:15')],
                [108, 'А калькулятор можно?', $ago(4, '20:31')],
                [$me, 'Нет, как и на ЕГЭ. Привыкаем считать сами.', $ago(4, '20:33')],
                [$me, 'Разбор пробника. Больше всего ошибок в 12-м и 15-м заданиях — посмотрите до следующего занятия.', $ago(1, '19:45'), [self::file('Пробник, разбор ошибок.pdf', 2516582)]],
                [106, 'Посмотрел. В 15-м перепутал знак, когда делил на отрицательное', $ago(1, '20:02')],
                [108, 'А можно ещё раз 18-е на занятии?', $ago(1, '20:10')],
            ]),
            '107' => $this->personalChat(World::student(107), 1007, [
                [107, 'Спасибо за разбор пробника!', $ago(1, '20:40')],
                [107, 'Теперь понятно, где я теряла баллы', $ago(1, '20:41')],
                [$me, 'Аминат, 78 баллов — уже хороший результат. Подтянем 13-е и 16-е, и будет 85+.', $ago(1, '20:55')],
            ]),
            (string) self::ARCHIVED => $this->personalChat(self::archivedStudent(), 1001, [
                [self::ARCHIVED, 'Сдал ОГЭ на пятёрку! Спасибо вам огромное за этот год', $now->copy()->subMonths(3)->setTimeFromTimeString('15:20')],
                [$me, 'Ахмед, поздравляю! Ты это заслужил. Удачи в 10 классе!', $now->copy()->subMonths(3)->setTimeFromTimeString('15:45')],
            ], archived: true),
        ];

        // Сообщения: [from, text, at, files?, unread?] → массивы с id (ключ чата * 100 + номер)
        foreach ($chats as $key => &$chat) {
            $base = ($key === 'support' ? 900 : (int) $key) * 100;
            $chat['messages'] = array_map(fn (array $m, int $i) => [
                'id' => $base + $i + 1,
                'from' => $m[0],
                'text' => $m[1],
                'at' => $m[2],
                'files' => $m[3] ?? [],
                'unread' => $m[4] ?? false,
                'read' => true,
            ], $chat['messages'], array_keys($chat['messages']));
        }

        return $chats;
    }

    /** Чат индивидуального занятия ученика (describe(): имя ученика, подпись — занятие и ближайшее время). */
    private function roomChat(int $studentId, array $messages): array
    {
        $roomId = World::roomOf($studentId);
        $student = World::student($studentId);

        return [
            'type' => 'person',
            'name' => $student->name,
            'sub' => World::ROOMS[$roomId][0] . $this->nextLesson($roomId),
            'link' => ['label' => 'Карточка ученика', 'url' => TeacherStudentsService::studentUrl($student)],
            'avatar' => $student,
            'room' => $roomId,
            'messages' => $messages,
        ];
    }

    private function groupChat(array $messages): array
    {
        $roomId = 206;

        return [
            'type' => 'group',
            'name' => World::ROOMS[$roomId][0],
            'sub' => plural_ru(count(World::ROOMS[$roomId][2]), 'ученик', 'ученика', 'учеников') . $this->nextLesson($roomId),
            'link' => ['label' => 'Занятие', 'url' => route('cabinet.teacher.lesson', $roomId)],
            'avatar' => null,
            'room' => $roomId,
            'messages' => $messages,
        ];
    }

    /** Личный чат (describePersonal()). */
    private function personalChat(User $student, int $chatId, array $messages, bool $archived = false): array
    {
        return [
            'type' => 'person',
            'name' => $student->name,
            'sub' => 'Ученик',
            'link' => $archived ? null : ['label' => 'Карточка ученика', 'url' => TeacherStudentsService::studentUrl($student)],
            'archived' => $archived,
            'avatar' => $student,
            'personal' => $chatId,
            'messages' => $messages,
        ];
    }

    /** « · занятие завтра в 17:30» — как MessengerService::nextLesson(). */
    private function nextLesson(int $roomId): string
    {
        $next = World::lessons(Carbon::now(), Carbon::now()->addDays(8))
            ->first(fn (array $l) => $l['roomId'] === $roomId && $l['start']->isFuture());

        return $next ? ' · занятие ' . HumanDate::at($next['start']) : '';
    }

    /** Вложение (MessengerService::files()): ссылка за пределы демо — demo-cabinet.js покажет тост, а не скачает файл. */
    private static function file(string $name, int $bytes): array
    {
        return [
            'name' => $name,
            'url' => url('/images/demo/' . rawurlencode($name)),
            'image' => false,
            'size' => $bytes >= 1048576
                ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' МБ'
                : max(1, (int) round($bytes / 1024)) . ' КБ',
        ];
    }

    private static function image(string $name): array
    {
        return ['name' => $name, 'url' => asset('images/demo/messages-solution.svg'), 'image' => true, 'size' => null];
    }
}
