<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Demo\World;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewShareCardGenerator;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Intervention\Image\Laravel\Facades\Image;

/**
 * «Отзывы» учителя — App\Livewire\Cabinet\Teacher\Reviews.
 *
 * Состояние — в адресе, под теми же именами, что свойства компонента: ?openId= (отзыв целиком),
 * ?shareId= (окно «Поделиться»), ?reportId=&reportReason= (жалоба), ?search= (поиск).
 * ?seen= — прочитанные в демо новые отзывы (открыли или поделились — отзыв уходит в «Все отзывы»).
 */
class Reviews extends Screen
{
    public const PATH = 'reviews';

    /** Состояния для DemoCabinetTest. */
    public const EXAMPLES = ['reviews', 'reviews?openId=301', 'reviews?shareId=302', 'reviews?reportId=304&reportReason=other',
        'reviews?search=' . 'Аушева', 'reviews?search=' . 'нет такого', 'reviews?seen=301'];

    /** Ученики без отзыва, у которых уже были занятия (Лейла и Хава) — для «Попросить учеников об отзыве». */
    private const ASKABLE = 2;

    /** Бывшие ученики (уже сдали экзамены): id => [фамилия, имя]. */
    private const FORMER = [
        109 => ['Хамхоев', 'Беслан'],
        110 => ['Яндиева', 'Танзила'],
        111 => ['Барахоев', 'Ибрагим'],
        112 => ['Албакова', 'Макка'],
        113 => ['Цороева', 'Хеди'],
        114 => ['Мержоев', 'Умар'],
    ];

    /** Отзывы: id => [ученик, оценка, текст, когда (минут назад), занятий с учителем]. Первый — новый (непрочитанный). */
    private const REVIEWS = [
        301 => [103, 5, "Раньше физику просто зубрил, а теперь понимаю, откуда берутся формулы. Зарема Ахмедовна разбирает каждую задачу, пока не станет ясно, и не ругает за ошибки.\n\nКонтрольную по динамике написал на пятёрку — первый раз за год.", 125, 18],
        302 => [102, 5, 'Готовлюсь к профильному ЕГЭ второй месяц. Пробник подняла с 54 до 78 баллов. Особенно помогли разборы второй части — теперь не боюсь задач с параметром.', 9 * 1440 + 200, 24],
        303 => [101, 5, 'Объясняет спокойно и понятно. После каждого занятия присылает домашнее задание, а потом разбирает ошибки. Квадратные уравнения наконец решаю без подсказок.', 16 * 1440 + 90, 31],
        304 => [106, 4, 'Групповые занятия по ЕГЭ проходят живо: много практики и разбор вариантов. Иногда хотелось бы больше времени на сложные задачи, но в целом очень доволен.', 24 * 1440 + 300, 22],
        305 => [107, 5, 'Спасибо за разбор пробника! Зарема Ахмедовна видит, где именно я теряю баллы, и даёт задания точно под это. За месяц перестала ошибаться в планиметрии.', 31 * 1440 + 60, 26],
        306 => [108, 5, 'Удобно, что задания и записи занятий в одном месте: пропустил занятие — посмотрел запись. Объясняет по делу, без воды.', 45 * 1440 + 400, 20],
        307 => [109, 5, 'Готовился к ОГЭ почти с нуля, в начале года решал на тройку. Сдал на пятёрку, 21 балл. Огромное спасибо за терпение!', 118 * 1440, 64],
        308 => [110, 5, 'Лучший учитель математики, который у меня был. Сложные темы объясняет на простых примерах и всегда отвечает на вопросы в сообщениях.', 140 * 1440, 48],
        309 => [111, 4, 'Хорошо подготовила к олимпиаде по физике — дошёл до регионального этапа. Задачи сложные, но интересные. Домашних заданий много, зато результат есть.', 175 * 1440, 37],
        310 => [112, 5, 'Сдала профильную математику на 86 баллов и поступила на бюджет. Без этих занятий не справилась бы. Спасибо!', 205 * 1440, 72],
        311 => [113, 5, 'Занималась весь девятый класс. Геометрия перестала быть страшной, на экзамене решила все задачи с чертежами.', 240 * 1440, 55],
        312 => [114, 4, 'Понравилось, что занятие можно перенести, если не успеваю после тренировки. Объяснения понятные, по алгебре подтянулся с тройки до четвёрки.', 270 * 1440, 29],
    ];

    public string $view = 'livewire.cabinet.teacher.reviews';

    public string $title = 'Отзывы';

    public ?string $active = 'reviews';

    public function modalParams(): array
    {
        return ['openId', 'shareId', 'reportId', 'reportReason', 'reportNote'];
    }

    public function actions(): array
    {
        $sendReport = filled($this->state('reportReason'))
            ? ['close' => true, 'set' => ['reported' => (string) $this->state('reportId', '')], 'toast' => 'Жалоба отправлена']
            : ['toast' => 'Выберите причину', 'tone' => 'danger'];

        return [
            // Открытие окна и «Поделиться» считаются прочтением — новый отзыв уходит в «Все отзывы»
            'read' => ['set' => ['openId' => '{0}'], 'add' => 'seen'],
            'share' => ['set' => ['shareId' => '{0}', 'openId' => null], 'add' => 'seen'],
            'shared' => ['add' => 'seen'],
            'askReport' => ['set' => ['reportId' => '{0}', 'openId' => null, 'reportReason' => null]],
            'sendReport' => $sendReport,
            'requestAll' => ['toast' => 'Попросили ' . plural_ru(self::ASKABLE, 'ученика', 'учеников', 'учеников') . ' оставить отзыв'],
        ];
    }

    public function data(): array
    {
        $search = trim((string) $this->state('search', ''));
        $seen = $this->stateInts('seen');
        $reported = $this->stateInts('reported');
        $pageLabel = 'serdal.ru/' . self::USERNAME;

        $rows = self::rows($pageLabel, $reported);
        $isFresh = fn (array $r) => $r['fresh'] && ! in_array($r['id'], $seen, true);
        $fresh = $rows->filter($isFresh)->values();
        $read = $rows->reject($isFresh)
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (array $r) => mb_stripos($r['name'], $search) !== false))
            ->values();

        $openId = $this->state('openId', 0);
        $shareId = $this->state('shareId', 0);
        $reportId = $this->state('reportId', 0);
        $byId = $rows->keyBy('id');

        $opened = $byId->get($openId);
        if ($opened) {
            $opened['meta'] = plural_ru(self::REVIEWS[$openId][4], 'занятие', 'занятия', 'занятий') . ' с вами';
        }

        $shared = $byId->get($shareId);
        if ($shared) {
            $shared['shareUrl'] = self::card($shareId);
        }

        return [
            'page' => route('tutors.show', self::USERNAME),
            'pageLabel' => $pageLabel,
            'fresh' => $fresh,
            'all' => $read,
            'more' => 0,
            'searching' => $search !== '',
            'searchable' => true,
            'summary' => self::summary(),
            'opened' => $opened,
            'shared' => $shared,
            'reported' => $byId->get($reportId),
            'reasons' => Review::REPORT_REASONS,
            'askable' => self::ASKABLE,
            // Публичные свойства компонента
            'limit' => 20,
            'openId' => $openId ?: null,
            'shareId' => $shareId ?: null,
            'reportId' => $reportId ?: null,
            'reportReason' => (string) $this->state('reportReason', ''),
            'reportNote' => '',
            'search' => $search,
        ];
    }

    /** Адрес публичной страницы демо-учителя (в World::teacher() username нет). */
    public const USERNAME = 'zarema-malsagova';

    /** Сводка «Ваша оценка» (TeacherReviewsService::summary) — её же показывает превью в профиле. */
    public static function summary(): array
    {
        $bars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach (self::REVIEWS as [, $rating]) {
            $bars[$rating]++;
        }
        $count = array_sum($bars);
        $sum = array_sum(array_map(fn ($r, $n) => $r * $n, array_keys($bars), $bars));

        return ['count' => $count, 'avg' => round($sum / $count, 1), 'bars' => $bars];
    }

    /** Строки как Reviews::row() настоящего компонента; 'fresh' — непрочитанный. */
    private static function rows(string $pageLabel, array $reported): Collection
    {
        return collect(self::REVIEWS)->map(function (array $r, int $id) use ($pageLabel, $reported) {
            [$studentId, $rating, $text, $minutes] = $r;
            $user = self::author($studentId);
            $at = Carbon::now()->subMinutes($minutes);

            return [
                'id' => $id,
                'name' => $user->name,
                'user' => $user,
                'rating' => $rating,
                'text' => $text,
                'date' => HumanDate::day($at),
                'at' => HumanDate::at($at),
                'reported' => in_array($id, $reported, true),
                // Картинку для сторис рисуем только для открытого окна «Поделиться» (card())
                'shareUrl' => '#',
                'caption' => '«' . trim($text) . '»' . "\n— " . $user->name . '. Все отзывы: ' . $pageLabel,
                'fresh' => $id === array_key_first(self::REVIEWS),
            ];
        })->values();
    }

    private static function author(int $id): User
    {
        if (isset(World::STUDENTS[$id])) {
            return World::student($id);
        }

        [$last, $first] = self::FORMER[$id];
        $user = new User;
        $user->forceFill(['id' => $id, 'name' => $last . ' ' . $first, 'last_name' => $last, 'first_name' => $first,
            'role' => User::ROLE_STUDENT, 'avatar' => null]);
        $user->exists = true;

        return $user;
    }

    /**
     * Картинка для сторис — настоящим ReviewShareCardGenerator (без базы: модели в памяти), уменьшенная
     * до 480 по ширине и вставленная в страницу data-адресом: настоящий адрес /reviews/{id}/share-card ходит в базу.
     */
    private static function card(int $id): string
    {
        [$studentId, $rating, $text] = self::REVIEWS[$id];
        $review = new Review;
        $review->forceFill(['id' => $id, 'rating' => $rating, 'text' => $text, 'is_rejected' => false]);
        $review->setRelation('user', self::author($studentId));
        $review->setRelation('teacher', World::teacher());

        try {
            $jpeg = app(ReviewShareCardGenerator::class)->generate($review);
            $small = Image::read($jpeg)->scale(width: 480)->toJpeg(quality: 60)->toString();
        } catch (\Throwable) {
            return '#';
        }

        return 'data:image/jpeg;base64,' . base64_encode($small);
    }
}
