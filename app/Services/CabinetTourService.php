<?php

namespace App\Services;

use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Тур по кабинету учителя и ученика. Показ и переходы — App\Livewire\Cabinet\Tour и resources/js/tour.js.
 *
 * Порядок — как меню в сайдбаре: для каждого раздела сначала пункт меню, следующим шагом — главное на его экране
 * (кнопка в шапке или фокус-блок). Потом общее: уведомления, партнёрка, «Помощь» (поддержка, тур, база знаний), профиль.
 *
 * Шаг: url — экран, на который перейти (null — показывать там, где человек сейчас: приветствие, пункты меню, общее);
 * targets — что подсветить, по порядку (data-tour="…" в разметке, берётся первый видимый элемент; ничего нет — окно по центру);
 * help — раздел базы знаний, где об этом подробно; reveal — что раскрыть на время шага (меню «Помощь»).
 */
class CabinetTourService
{
    /** Разделы на нижней панели телефона (остальные — в «Ещё»). */
    private const MOBILE_TABS = ['home', 'today', 'schedule', 'tasks', 'messages'];

    /** Тур есть у учителя (после «Первых шагов») и у ученика. */
    public static function available(?User $user): bool
    {
        return match ($user?->role) {
            User::ROLE_STUDENT => true,
            User::ROLE_TUTOR => (bool) $user->is_profile_completed,
            default => false,
        };
    }

    /** Открыть тур самим — если его ещё ни разу не предлагали. */
    public static function shouldAutoStart(?User $user): bool
    {
        return self::available($user) && $user->tour_seen_at === null;
    }

    public static function markSeen(User $user): void
    {
        if ($user->tour_seen_at === null) {
            $user->forceFill(['tour_seen_at' => now()])->save();
        }
    }

    /** Ссылка «Тур по кабинету»: главный экран своей роли с ?tour=1. */
    public static function startUrl(User $user): string
    {
        $home = $user->role === User::ROLE_STUDENT ? 'cabinet.student.home' : 'cabinet.teacher.today';

        return route($home, ['tour' => 1]);
    }

    /** @return list<array{url: ?string, targets: list<string>, title: string, text: string, help: ?string}> */
    public function steps(User $user): array
    {
        $student = $user->role === User::ROLE_STUDENT;
        $audience = $student ? HelpCategory::AUDIENCE_STUDENT : HelpCategory::AUDIENCE_TUTOR;
        $helpHome = route('help.section', HelpCategory::AUDIENCE_SLUGS[$audience]);
        $help = HelpCategory::query()->published()->where('audience', $audience)->get()->keyBy('name');
        // Раздел переименовали или сняли — ведём в справку своей роли
        $helpUrl = fn (?string $category) => $category ? ($help->get($category)?->url ?? $helpHome) : null;
        $step = fn (?string $url, array $targets, string $title, string $text, ?string $category = null, ?string $reveal = null) => [
            'url' => $url, 'targets' => $targets, 'title' => $title, 'text' => $text, 'help' => $helpUrl($category), 'reveal' => $reveal,
        ];

        $steps = [$step(null, [], 'Покажем кабинет', ($student
            ? 'За пару минут пройдём по меню: где расписание, задания от учителя, материалы и оплата.'
            : 'За пару минут пройдём по меню: где расписание, ученики, задания и материалы и с чего начать работу.')
            . ' Пройти тур снова можно в любой момент — он есть в меню.')];

        foreach ($student ? $this->studentSections() : $this->teacherSections() as $s) {
            if (! Route::has($s['route'])) {
                continue;
            }
            // Пункт меню — там, где человек сейчас; на телефоне — вкладка нижней панели или «Ещё»
            $steps[] = $step(null, ['nav-' . $s['key'], in_array($s['key'], self::MOBILE_TABS, true) ? 'tab-' . $s['key'] : 'more'], $s['label'], $s['menu']);
            // Главное на экране раздела
            $steps[] = $step(route($s['route']), $s['targets'], $s['title'], $s['text'], $s['help']);
        }

        $steps[] = $step(null, ['bell'], 'Уведомления', ($student
            ? 'Новые задания, сообщения, оплаты, статьи вашего учителя и напоминание за 15 минут до занятия.'
            : 'Новые работы, сообщения, оплаты, комментарии к вашим статьям и напоминание за 15 минут до занятия.') . ' Красная точка — есть непрочитанные.', $student ? 'Отзывы, сообщения и поддержка' : 'Отзывы, сообщения и уведомления');
        if (! $student && ReferralService::enabled() && Route::has('cabinet.teacher.referrals')) {
            $steps[] = $step(null, ['referrals', 'more'], 'Пригласить коллег', 'Позовите знакомого учителя на Serdal — когда он оплатит тариф, вы получите бонусные занятия.', 'Тариф и партнёрская программа');
        }
        // Меню «Помощь» тур раскрывает сам (reveal), на телефоне — «Ещё»
        $steps[] = $step(null, ['help-menu', 'help', 'more'], 'Помощь', 'Чат с поддержкой — ответим на любой вопрос по кабинету, база знаний с инструкциями и этот тур: пройдите его снова, если что-то забудется.', null, 'help');
        $steps[] = $student
            ? $step(null, ['nav-profile', 'more'], 'Профиль', 'Имя, класс, пароль и уведомления в браузере. Здесь же — выход из кабинета.', 'Начало работы')
            : $step(null, ['nav-profile', 'more'], 'Профиль и тариф', 'Фото, предметы и цены — их видят ученики на вашей странице в каталоге. Здесь же уведомления, тариф и выход из кабинета.', 'Начало работы');

        $steps[] = ['url' => null, 'targets' => [], 'reveal' => null, 'title' => 'Вот и всё',
            'text' => 'Подробные инструкции по каждому разделу — в базе знаний. Если что-то не получается, напишите в «Поддержку» — поможем.',
            'help' => $helpHome];

        return $steps;
    }

    /** Разделы учителя в порядке меню. */
    private function teacherSections(): array
    {
        return [
            ['key' => 'today', 'route' => 'cabinet.teacher.today', 'label' => 'Сегодня',
                'menu' => 'Главный экран: сюда вы попадаете после входа, с него начинается рабочий день.',
                'targets' => ['focus', 'actions'], 'title' => 'Что сделать сегодня', 'help' => 'Занятие в классе',
                'text' => 'Здесь занятия на сегодня — когда подойдёт время, у занятия появится кнопка «Начать занятие». Пока занятий нет, здесь «Первые шаги»: они подскажут, что сделать дальше.'],
            ['key' => 'schedule', 'route' => 'cabinet.teacher.schedule', 'label' => 'Расписание',
                'menu' => 'Все занятия — списком, по неделям или на месяц.',
                'targets' => ['actions'], 'title' => 'Запланировать занятие', 'help' => 'Расписание',
                'text' => 'Выберите ученика или группу, день и время. Повторяющееся занятие само появится в расписании на каждой неделе, а ученики получат уведомление.'],
            ['key' => 'messages', 'route' => 'cabinet.teacher.messages', 'label' => 'Сообщения',
                'menu' => 'Переписка с учениками и группами.',
                'targets' => ['support-chat', 'search'], 'title' => 'Чат поддержки', 'help' => 'Отзывы, сообщения и уведомления',
                'text' => 'Он всегда закреплён вверху. Если что-то не получается, напишите сюда — поможем разобраться.'],
            ['key' => 'students', 'route' => 'cabinet.teacher.students', 'label' => 'Ученики',
                'menu' => 'Ваши ученики и группы, их занятия и оплата.',
                'targets' => ['actions'], 'title' => 'Пригласить ученика', 'help' => 'Ученики',
                'text' => 'Отправьте ученику ссылку-приглашение в мессенджере или на почту. После регистрации он появится в списке.'],
            ['key' => 'tasks', 'route' => 'cabinet.teacher.tasks', 'label' => 'Задания',
                'menu' => 'Задания ученикам и работы на проверку. Красный счётчик покажет, сколько работ ждут вас.',
                'targets' => ['actions'], 'title' => 'Выдать задание', 'help' => 'Задания',
                'text' => 'Выдайте задание ученику или группе. Сданные работы соберутся во вкладке «Нужно проверить» — там оценка, комментарий и пометки прямо на фото.'],
            ['key' => 'materials', 'route' => 'cabinet.teacher.materials', 'label' => 'Материалы',
                'menu' => 'Ваша библиотека файлов для занятий.',
                'targets' => ['actions'], 'title' => 'Загрузить', 'help' => 'Материалы',
                'text' => 'Загрузите файлы, разложите их по папкам и откройте нужным ученикам — они сразу увидят материалы у себя.'],
            ['key' => 'recordings', 'route' => 'cabinet.teacher.recordings', 'label' => 'Записи',
                'menu' => 'Записи проведённых занятий.',
                'targets' => ['focus', 'empty', 'search'], 'title' => 'Записи занятий', 'help' => 'Записи',
                'text' => 'Запись появляется здесь после окончания занятия: её можно посмотреть и скачать. Ученики тоже её видят — удобно повторить пройденное.'],
            ['key' => 'reviews', 'route' => 'cabinet.teacher.reviews', 'label' => 'Отзывы',
                'menu' => 'Отзывы учеников — они видны на вашей странице в каталоге.',
                'targets' => ['focus'], 'title' => 'Отзывы учеников', 'help' => 'Отзывы, сообщения и уведомления',
                'text' => 'Новые отзывы появляются здесь, ими можно поделиться. Пока отзывов нет — попросите учеников оставить отзыв после занятия.'],
            ['key' => 'blog', 'route' => 'cabinet.teacher.blog', 'label' => 'Мои статьи',
                'menu' => 'Ваши статьи в блоге Serdal: черновики, на проверке и опубликованные.',
                'targets' => ['actions', 'empty'], 'title' => 'Написать статью', 'help' => 'Блог',
                'text' => 'Напишите статью о своем предмете — после проверки она выйдет в блоге с вашим именем и ссылкой на вашу страницу, а ученики получат уведомление.'],
        ];
    }

    /** Разделы ученика в порядке меню. */
    private function studentSections(): array
    {
        return [
            ['key' => 'home', 'route' => 'cabinet.student.home', 'label' => 'Главная',
                'menu' => 'Главный экран: сюда вы попадаете после входа.',
                'targets' => ['focus'], 'title' => 'Ближайшее занятие', 'help' => 'Занятия',
                'text' => 'За 15 минут до начала здесь появится кнопка «Войти в класс». Ниже — задания, оплата и ваши учителя.'],
            ['key' => 'schedule', 'route' => 'cabinet.student.schedule', 'label' => 'Расписание',
                'menu' => 'Все ваши занятия с датой и временем.',
                'targets' => ['actions', 'focus'], 'title' => 'Google Календарь', 'help' => 'Занятия',
                'text' => 'Подключите Google Календарь — занятия появятся в нём сами, и телефон напомнит о них заранее.'],
            ['key' => 'tasks', 'route' => 'cabinet.student.tasks', 'label' => 'Задания',
                'menu' => 'Задания от учителя, оценки и успеваемость.',
                'targets' => ['focus', 'empty'], 'title' => 'Сдать работу', 'help' => 'Задания',
                'text' => 'Откройте задание, приложите фото или файл и нажмите «Сдать работу». Оценка и комментарий учителя придут сюда же.'],
            ['key' => 'messages', 'route' => 'cabinet.student.messages', 'label' => 'Сообщения',
                'menu' => 'Переписка с учителем.',
                'targets' => ['support-chat', 'search'], 'title' => 'Чат поддержки', 'help' => 'Отзывы, сообщения и поддержка',
                'text' => 'Он всегда закреплён вверху. Если что-то не получается, напишите сюда — поможем разобраться.'],
            ['key' => 'materials', 'route' => 'cabinet.student.materials', 'label' => 'Материалы',
                'menu' => 'Файлы, которыми поделился учитель.',
                'targets' => ['focus', 'empty', 'search'], 'title' => 'Файлы от учителя', 'help' => 'Материалы',
                'text' => 'Конспекты, презентации и учебники. Нажмите на файл, чтобы открыть или сохранить его.'],
            ['key' => 'recordings', 'route' => 'cabinet.student.recordings', 'label' => 'Записи',
                'menu' => 'Записи прошедших занятий.',
                'targets' => ['focus', 'empty', 'search'], 'title' => 'Записи занятий', 'help' => 'Занятия',
                'text' => 'Пересмотрите занятие, если что-то пропустили или хотите повторить.'],
            ['key' => 'payments', 'route' => 'cabinet.student.payments', 'label' => 'Оплата',
                'menu' => 'Счета за занятия и история оплат.',
                'targets' => ['focus', 'empty'], 'title' => 'Сообщить об оплате', 'help' => 'Оплата занятий',
                'text' => 'Заплатили учителю — нажмите «Сообщить об оплате» и приложите чек. Учитель подтвердит оплату.'],
        ];
    }
}
