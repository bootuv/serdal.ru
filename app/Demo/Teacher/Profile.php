<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Demo\World;
use App\Models\LessonType;
use App\Models\Review;
use App\Models\Tariff;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\PlatformReviewService;
use App\Services\TeacherProfileService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * «Профиль и цены» учителя — App\Livewire\Cabinet\Teacher\Profile со вкладками ?tab=prices|notify|account.
 *
 * Состояние — в адресе под именами свойств компонента: tab, last_name/first_name/middle_name (поля анкеты),
 * addSubject/addDirect (выбранное в «Добавить»), saved; цены — priceId (окно цены), priceType, pricePayment,
 * deletePriceId; почта и пароль (Cabinet\Account) — editing, step, codeResent; отзыв о платформе — prOpen, rating.
 * Убранные из анкеты предметы, направления и переключённые классы — subjOff, dirOff, gradeFlip.
 */
class Profile extends Screen
{
    public const PATH = 'profile';

    /** Состояния для DemoCabinetTest. */
    public const EXAMPLES = ['profile', 'profile?saved=1&first_name=Зарема&addSubject=7&subjOff=10&gradeFlip=5',
        'profile?prOpen=1', 'profile?tab=prices', 'profile?tab=prices&priceId=2&pricePayment=per_lesson',
        'profile?tab=prices&priceId=1', 'profile?tab=prices&deletePriceId=1',
        'profile?tab=notify', 'profile?tab=account', 'profile?tab=account&editing=email',
        'profile?tab=account&editing=password&step=code'];

    /** Предметы и направления платформы (таблицы subjects, directs): id => название. */
    private const SUBJECTS = [9 => 'Английский язык', 8 => 'Биология', 11 => 'История', 2 => 'Литература', 3 => 'Математика',
        1 => 'Русский язык', 10 => 'Физика', 7 => 'Химия'];

    private const DIRECTS = [6 => 'IT-наставничество', 8 => 'ВПР', 7 => 'ДВИ', 1 => 'ЕГЭ', 5 => 'Начальная школа', 2 => 'ОГЭ',
        3 => 'Олимпиады', 4 => 'ОПР', 9 => 'Язык с нуля'];

    /** Анкета демо-учителя. */
    private const MY_SUBJECTS = [3, 10];

    private const MY_DIRECTS = [1, 2, 3];

    private const MY_GRADES = ['7', '8', '9', '10', '11'];

    private const ABOUT = '<p>Учитель математики и физики, готовлю к ОГЭ и ЕГЭ. Занимаюсь с учениками 7–11 классов: закрываем пробелы, разбираем сложные темы и решаем задачи экзаменационного формата.</p>'
        . '<p>На занятиях объясняю на простых примерах и не двигаюсь дальше, пока тема не станет понятной. После каждого занятия — домашнее задание и разбор ошибок.</p>';

    private const EXTRA = '<ul><li>Ингушский государственный университет, физико-математический факультет, учитель математики и физики</li>'
        . '<li>12 лет преподаю в школе, 8 лет занимаюсь с учениками индивидуально</li>'
        . '<li>Эксперт ОГЭ по математике; средний балл моих учеников на профильном ЕГЭ — 78</li></ul>';

    private const PLATFORM_REVIEW = 'Перевела на Serdal всех учеников: расписание, домашние задания и оплата теперь в одном месте. Ученики сами видят, когда занятие и что задано, а я трачу меньше времени на переписку.';

    public string $view = 'livewire.cabinet.teacher.profile';

    public string $title = 'Профиль и цены';

    public ?string $active = null;

    public function modalParams(): array
    {
        return ['priceId', 'priceType', 'pricePayment', 'deletePriceId', 'editing', 'step', 'codeResent', 'prOpen', 'rating'];
    }

    public function actions(): array
    {
        // Почта и пароль: тост — как у Cabinet\Account::confirm для того, что меняют
        $confirmed = $this->state('editing') === 'email'
            ? 'Почта изменена — теперь входите с новой почтой'
            : 'Пароль изменён. На других устройствах нужно будет войти заново';

        return [
            // Профиль. «Сохранить» есть и у анкеты, и в окне отзыва о платформе (PlatformReview::save) — различаем по открытому окну
            'save' => $this->state('prOpen', false)
                ? ['close' => true, 'toast' => 'Спасибо! Отзыв появится на сайте после проверки']
                : ['set' => ['saved' => '1']],
            'removeSubject' => ['add' => 'subjOff', 'set' => ['addSubject' => null]],
            'removeDirect' => ['add' => 'dirOff', 'set' => ['addDirect' => null]],
            'toggleGrade' => ['toggle' => 'gradeFlip', 'set' => ['saved' => null]],
            'deletePhoto' => ['toast' => 'Фото удалено'],
            // Цены
            'editPrice' => ['set' => ['priceId' => '{0}']],
            'closePrice' => ['close' => true],
            'savePrice' => ['close' => true, 'toast' => 'Цена сохранена'],
            'confirmDeletePrice' => ['set' => ['deletePriceId' => (string) $this->state('priceId', ''), 'priceId' => null, 'pricePayment' => null, 'priceType' => null]],
            'deletePrice' => ['close' => true, 'toast' => 'Цена удалена'],
            // Почта и пароль (Cabinet\Account)
            'open' => ['set' => ['editing' => '{0}', 'step' => null, 'codeResent' => null]],
            'cancel' => ['close' => true],
            'sendCode' => ['set' => ['step' => 'code']],
            'resendCode' => ['set' => ['codeResent' => '1']],
            'confirm' => ['close' => true, 'toast' => $confirmed],
            // Отзыв о платформе (PlatformReview)
            'openForm' => ['set' => ['prOpen' => '1']],
            'closeForm' => ['close' => true],
        ];
    }

    /** $wire.… в шаблоне: редактор «Обо мне» (entangle), звёзды в окне отзыва о платформе. */
    public function props(): array
    {
        return [
            'about' => self::ABOUT,
            'extra_info' => self::EXTRA,
            'rating' => $this->state('rating', 5),
            'promptRating' => 0,
            'isSubscribed' => true,
        ];
    }

    public function components(): array
    {
        $teacher = World::teacher();

        $map = [
            'cabinet.teacher.platform-review' => ['livewire.cabinet.teacher.platform-review', $this->platformReview()],
            'push-notification-toggle' => ['demo.teacher.push-switch', []],
            'cabinet.account' => ['livewire.cabinet.account', [
                'user' => $teacher,
                'embedded' => true,
                'editing' => $this->state('editing'),
                'step' => $this->state('step', 'form'),
                'codeResent' => $this->state('codeResent', false),
                'sentTo' => $this->state('editing') === 'email' ? 'zarema.m@example.com' : $teacher->email,
                'ttl' => 30,
                'newEmail' => '',
                'currentPassword' => '',
                'newPassword' => '',
                'verification_code' => '',
            ]],
        ];

        // Все подмены — один класс Stub, а Livewire называет его по первому зарегистрированному имени:
        // любой вложенный компонент рисует первую запись. На каждой вкладке вложенный компонент один —
        // ставим его первым
        $first = ['profile' => 'cabinet.teacher.platform-review', 'notify' => 'push-notification-toggle', 'account' => 'cabinet.account'][$this->state('tab', 'profile')] ?? null;

        return $first ? [$first => $map[$first]] + $map : $map;
    }

    public function data(): array
    {
        $teacher = World::teacher();
        $teacher->username = Reviews::USERNAME;
        $teacher->setRelation('lessonTypes', self::prices());

        $tab = $this->state('tab', 'profile');
        if (! in_array($tab, ['profile', 'prices', 'notify', 'account'], true)) {
            $tab = 'profile';
        }

        $subjects = $this->picked(self::MY_SUBJECTS, 'subjOff', 'addSubject');
        $directs = $this->picked(self::MY_DIRECTS, 'dirOff', 'addDirect');
        $flip = array_map('strval', $this->state('gradeFlip', []));
        $grades = TeacherProfileService::gradesForForm(array_merge(array_diff(self::MY_GRADES, $flip), array_diff($flip, self::MY_GRADES)));

        $form = [
            'last_name' => (string) $this->state('last_name', $teacher->last_name),
            'first_name' => (string) $this->state('first_name', $teacher->first_name),
            'middle_name' => (string) $this->state('middle_name', 'Ахмедовна'),
        ];

        $priceId = $this->state('priceId', 0);
        $editing = $priceId ? self::prices()->firstWhere('id', $priceId) : null;
        $priceType = (string) $this->state('priceType', $editing?->type ?? LessonType::TYPE_INDIVIDUAL);
        $pricePayment = (string) $this->state('pricePayment', $editing?->payment_type
            ?? ($priceType === LessonType::TYPE_GROUP ? LessonType::PAYMENT_MONTHLY : LessonType::PAYMENT_PER_LESSON));

        $tariff = new Tariff;
        $tariff->forceFill(['id' => 3, 'name' => 'Профи', 'max_participants' => 12]);

        return [
            'user' => $teacher,
            'subjectOptions' => collect(self::SUBJECTS),
            'directOptions' => collect(self::DIRECTS),
            'gradeOptions' => TeacherProfileService::GRADES,
            'preview' => $this->preview($teacher, $form, $subjects, $directs, $grades),
            'prices' => self::prices(),
            'canAddPrice' => false, // обе цены (индивидуальная и групповая) уже есть
            'priceTypes' => $editing ? [$editing->type => LessonType::TYPES[$editing->type]] : [],
            'blockAfter' => PaymentRecordService::BLOCK_AFTER_LESSONS,
            'groupLimit' => $tariff,
            'subscriptionUrl' => route('cabinet.teacher.subscription'),
            // Публичные свойства компонента
            'tab' => $tab,
            'photo' => null,
            'removePhoto' => false,
            'subjects' => $subjects,
            'directs' => $directs,
            'grades' => $grades,
            'about' => self::ABOUT,
            'extra_info' => self::EXTRA,
            'phone' => '+7 928 093-41-27',
            'whatsup' => '+7 928 093-41-27',
            'telegram' => 'zarema_math',
            'addSubject' => '',
            'addDirect' => '',
            'saved' => $this->state('saved', false),
            'priceOpen' => (bool) $editing,
            'priceId' => $editing?->id,
            'priceType' => $priceType,
            'pricePayment' => $pricePayment,
            'price' => $editing ? (string) (int) $editing->price : '',
            'priceDuration' => $editing->duration ?? 60,
            'priceCount' => $editing->count_per_week ?? ($pricePayment === LessonType::PAYMENT_MONTHLY ? 4 : ''),
            'priceDueDays' => $editing->payment_due_days ?? 3,
            'priceDueDay' => $editing->payment_due_day ?? 5,
            'deletePriceId' => $this->state('deletePriceId', 0) ?: null,
            // Поля окна отзыва о платформе (wire:model во вложенном компоненте заполняет demo-cabinet.js по данным экрана)
            'text' => self::PLATFORM_REVIEW,
            'showOnSite' => true,
        ] + $form;
    }

    /** Цены — как World::ROOMS: индивидуальные 1500 ₽ за 60 минут, группа 5000 ₽ в месяц (4 раза в неделю по 90 минут). */
    private static function prices(): Collection
    {
        return collect([
            [1, LessonType::TYPE_INDIVIDUAL, LessonType::PAYMENT_PER_LESSON, 1500, 60, null, 3, null],
            [2, LessonType::TYPE_GROUP, LessonType::PAYMENT_MONTHLY, 5000, 90, 4, null, 5],
        ])->map(function (array $p) {
            $lt = new LessonType;
            $lt->forceFill(array_combine(['id', 'type', 'payment_type', 'price', 'duration', 'count_per_week', 'payment_due_days', 'payment_due_day'], $p)
                + ['user_id' => World::TEACHER_ID]);
            $lt->exists = true;

            return $lt;
        });
    }

    /** Выбранные id: анкета − убранные (off) + добавленный в «Добавить» (add). */
    private function picked(array $mine, string $off, string $add): array
    {
        $ids = array_values(array_diff($mine, $this->stateInts($off)));
        $added = (int) $this->state($add, 0);
        if ($added && ! in_array($added, $ids, true)) {
            $ids[] = $added;
        }

        return $ids;
    }

    /** Карточка «Так вас видят в каталоге» — как Profile::preview(), без базы. */
    private function preview(User $teacher, array $form, array $subjects, array $directs, array $grades): array
    {
        $summary = Reviews::summary();
        $cheapest = $teacher->cheapestLesson();
        $gradeUser = new User(['grade' => $grades]);

        return [
            'name' => implode(' ', array_filter([$form['last_name'], $form['first_name'], $form['middle_name']])) ?: $teacher->name,
            'directs' => collect($directs)->map(fn ($id) => self::DIRECTS[$id] ?? null)->filter()->values(),
            'rating' => number_format($summary['avg'], 1, ',', ''),
            'reviews' => $summary['count'],
            'price' => $cheapest ? ($cheapest->isMonthly() ? '≈ ' : 'от ') . number_format($cheapest->pricePerLesson(), 0, ',', ' ') . ' ₽' : null,
            'subjects' => collect($subjects)->map(fn ($id) => self::SUBJECTS[$id] ?? null)->filter()->values()
                ->map(fn ($name, $i) => $i > 0 ? mb_strtolower($name) : $name)->implode(', '),
            'grades' => $gradeUser->displayGrade,
            'url' => route('tutors.show', ['username' => Reviews::USERNAME]),
        ];
    }

    /** Карточка «Отзыв о Serdal» (PlatformReview): отзыв оставлен и опубликован; ?prOpen=1 — окно изменения. */
    private function platformReview(): array
    {
        $review = new Review;
        $review->forceFill([
            'id' => 900,
            'rating' => 5,
            'text' => self::PLATFORM_REVIEW,
            'show_on_site' => true,
            'approved_at' => Carbon::now()->subDays(38),
            'updated_at' => Carbon::now()->subDays(40),
        ]);
        $review->exists = true;

        $open = $this->state('prOpen', false);

        return [
            'visible' => true,
            'prompt' => false,
            'open' => $open,
            'review' => $review,
            'status' => PlatformReviewService::status($review),
            'updated' => HumanDate::date($review->updated_at),
            'stars' => ['', '1 — очень плохо', '2 — плохо', '3 — нормально', '4 — хорошо', '5 — отлично'],
            'publicUrl' => route('reviews'),
            'rating' => $this->state('rating', 5),
            'promptRating' => 0,
            'text' => $review->text,
            'showOnSite' => true,
        ];
    }
}
