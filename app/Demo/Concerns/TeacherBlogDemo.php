<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use App\Models\BlogPost;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Статьи учителя в демо (экраны «Мои статьи», «Статистика статей», статья): одна на сайте, одна на проверке, черновик.
 * Просмотры по дням считаются формулой от даты — без базы, но одинаково на всех экранах.
 */
trait TeacherBlogDemo
{
    protected const BLOG_PUBLISHED = 301;
    protected const BLOG_PENDING = 302;
    protected const BLOG_DRAFT = 303;

    /** Сколько дней назад вышла опубликованная статья. */
    protected const BLOG_PUBLISHED_DAYS = 41;

    /** Подписчики автора: всего и новые за 7 / 30 / 90 дней. */
    protected const BLOG_FOLLOWERS = [7 => 2, 30 => 7, 90 => 12];

    /** @return array<int, BlogPost> id => статья (в памяти) */
    protected static function blogPosts(): array
    {
        $now = Carbon::now();
        $rows = [
            self::BLOG_PENDING => [
                'title' => 'Производная без страха: объясняю на пальцах',
                'slug' => 'proizvodnaya-bez-straha',
                'excerpt' => 'Что такое производная, зачем она нужна в ЕГЭ и как перестать её бояться — на примерах из жизни и задачах из второй части.',
                'tags' => ['ЕГЭ', 'Математика', 'Алгебра'],
                'body' => self::pendingBody(),
                'published_at' => null,
                'review_status' => BlogPost::REVIEW_PENDING,
                'submitted_at' => $now->copy()->subDay()->setTime(22, 40),
                'updated_at' => $now->copy()->subDay()->setTime(22, 40),
            ],
            self::BLOG_DRAFT => [
                'title' => 'ОГЭ по математике за три месяца: план подготовки',
                'slug' => 'oge-po-matematike-za-tri-mesyatsa',
                'excerpt' => '',
                'tags' => ['ОГЭ', 'Математика'],
                'body' => self::draftBody(),
                'published_at' => null,
                'review_status' => BlogPost::REVIEW_DRAFT,
                'submitted_at' => null,
                'updated_at' => $now->copy()->subDays(3)->setTime(23, 5),
            ],
            self::BLOG_PUBLISHED => [
                'title' => 'Задачи на движение: пять шагов к решению',
                'slug' => 'zadachi-na-dvizhenie-pyat-shagov',
                'excerpt' => 'Как перестать путаться в скоростях и встречном движении: схема, таблица и одно уравнение. Разбираем на задачах из ОГЭ.',
                'tags' => ['ОГЭ', 'Математика', 'Текстовые задачи'],
                'body' => self::publishedBody(),
                'published_at' => $now->copy()->subDays(self::BLOG_PUBLISHED_DAYS)->setTime(10, 0),
                'review_status' => BlogPost::REVIEW_DRAFT,
                'submitted_at' => $now->copy()->subDays(self::BLOG_PUBLISHED_DAYS + 1)->setTime(21, 12),
                'updated_at' => $now->copy()->subDays(self::BLOG_PUBLISHED_DAYS)->setTime(10, 0),
            ],
        ];

        $teacher = World::teacher();
        $posts = [];
        foreach ($rows as $id => $row) {
            $post = new BlogPost;
            $post->forceFill([
                'id' => $id,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'excerpt' => $row['excerpt'],
                'cover_url' => null,
                'body' => $row['body'],
                'published_at' => $row['published_at'],
                'created_by' => $teacher->id,
                'author_id' => $teacher->id,
                'review_status' => $row['review_status'],
                'review_note' => null,
                'submitted_at' => $row['submitted_at'],
                'views_count' => $id === self::BLOG_PUBLISHED ? self::blogViewsTotal() : 0,
                'likes_count' => $id === self::BLOG_PUBLISHED ? 23 : 0,
                'comments_count' => $id === self::BLOG_PUBLISHED ? 9 : 0,
                'created_at' => $row['updated_at']->copy()->subDays(2),
                'updated_at' => $row['updated_at'],
            ]);
            $post->exists = true;
            $post->setRelation('author', $teacher);
            $post->setRelation('tags', collect($row['tags'])->map(fn ($name) => (object) ['name' => $name]));
            $posts[$id] = $post;
        }

        return $posts;
    }

    protected static function blogPost(int $id): ?BlogPost
    {
        return self::blogPosts()[$id] ?? null;
    }

    /** Просмотры опубликованной статьи за день ($ago дней назад): всплеск после выхода, дальше — поиск. */
    protected static function blogViewsOn(int $ago): int
    {
        $age = self::BLOG_PUBLISHED_DAYS - $ago; // дней с выхода
        if ($age < 0) {
            return 0;
        }

        $views = 7 + 46 * exp(-$age / 3) + $age * 0.18 + (($age * 37) % 7) - 3;
        $weekday = Carbon::today()->subDays($ago)->dayOfWeek;
        if (in_array($weekday, [5, 6], true)) {
            $views *= 0.7; // в пятницу и субботу читают меньше
        }
        if ($ago === 0) {
            $views *= 0.6; // сегодня — день ещё не закончился
        }

        return max(1, (int) round($views));
    }

    protected static function blogViewsTotal(): int
    {
        $sum = 0;
        for ($ago = 0; $ago <= self::BLOG_PUBLISHED_DAYS; $ago++) {
            $sum += self::blogViewsOn($ago);
        }

        return $sum;
    }

    /**
     * Отчёт как BlogStatsService::report(): за $days дней по статьям учителя или по одной статье ($postId).
     * Статья на сайте одна — по ней и все цифры.
     */
    protected static function blogReport(int $days, ?int $postId): array
    {
        $today = CarbonImmutable::today();
        $series = [];
        $views = 0;
        for ($ago = $days - 1; $ago >= 0; $ago--) {
            $d = $today->subDays($ago);
            $v = self::blogViewsOn($ago);
            $views += $v;
            $series[] = [
                'label' => $d->isoFormat('D MMM'),
                'value' => $v,
                'hint' => \App\Support\HumanDate::date($d) . ' — ' . plural_ru($v, 'просмотр', 'просмотра', 'просмотров'),
            ];
        }
        $previous = 0;
        for ($ago = $days; $ago < $days * 2; $ago++) {
            $previous += self::blogViewsOn($ago);
        }

        // Источники — доли от просмотров за период
        $shares = ['Поиск' => 0.49, 'Соцсети и мессенджеры' => 0.23, 'С сайта Serdal' => 0.17, 'Прямые заходы' => 0.08];
        $sources = collect($shares)->map(fn ($share, $label) => ['label' => $label, 'value' => (int) round($views * $share)])->values();
        $sources->push(['label' => 'Другие сайты', 'value' => max(0, $views - $sources->sum('value'))]);

        $scale = fn (int $all) => (int) round($all * min(1, $views / max(1, self::blogViewsTotal())));
        $post = self::blogPost(self::BLOG_PUBLISHED);

        return [
            'views' => $views,
            'previous' => $previous,
            'days' => $series,
            'sources' => $sources->filter(fn ($s) => $s['value'] > 0)->sortByDesc('value')->values()->all(),
            'likes' => $scale(23),
            'comments' => $scale(9),
            'followers' => $postId ? null : (self::BLOG_FOLLOWERS[$days] ?? 0),
            'posts' => $postId ? collect() : collect([[
                'id' => $post->id,
                'title' => $post->title,
                'url' => $post->url,
                'author' => $post->authorName(),
                'views' => $views,
                'total' => (int) $post->views_count,
                'likes' => 23,
                'comments' => 9,
            ]]),
            'authors' => collect(),
            'since' => null,
        ];
    }

    private static function publishedBody(): string
    {
        return <<<'HTML'
<p>Задачи на движение пугают учеников больше, чем они того заслуживают. В ОГЭ это обычно задача № 21 на два балла, и почти все такие задачи решаются по одной схеме. За годы занятий я свела её к пяти шагам — расскажу, как объясняю их своим ученикам.</p>
<h2>Шаг 1. Нарисуйте схему</h2>
<p>Отрезок — дорога, стрелки — кто куда едет, флажок — место встречи. Схема занимает полминуты, но сразу видно, движутся объекты навстречу друг другу, вдогонку или в одну сторону.</p>
<h2>Шаг 2. Составьте таблицу</h2>
<p>Три столбца: скорость, время, расстояние. Строк — столько, сколько участников движения. Всё, что известно, вписываем сразу, неизвестное обозначаем через <strong>x</strong>.</p>
<aside class="callout">Главное правило: в каждой строке расстояние = скорость × время. Если одна клетка пустая, её всегда можно выразить через две другие.</aside>
<h2>Шаг 3. Найдите, что равно чему</h2>
<p>Ищем в условии слова «встретились», «догнал», «прибыл одновременно» — это и есть уравнение. При встречном движении расстояния складываются, при движении вдогонку — вычитаются.</p>
<h2>Шаг 4. Решите уравнение</h2>
<p>Чаще всего получается дробно-рациональное уравнение. Не забываем про область допустимых значений: скорость не может быть отрицательной, а время — нулевым.</p>
<h2>Шаг 5. Проверьте ответ по смыслу</h2>
<ol><li>Отвечает ли число на вопрос задачи — скорость, а не время?</li><li>Правильные ли единицы — км/ч или м/с?</li><li>Реалистичен ли ответ — пешеход не идёт со скоростью 40 км/ч.</li></ol>
<p>После десятка задач по этой схеме ученики перестают бояться текстовых задач и решают их в среднем за 6–7 минут. Попробуйте — и напишите в комментариях, какая задача на движение кажется вам самой сложной.</p>
HTML;
    }

    private static function pendingBody(): string
    {
        return <<<'HTML'
<p>Слово «производная» звучит страшно, но идея за ним простая: <strong>производная показывает, как быстро что-то меняется</strong>. Скорость машины — производная пути по времени. Вот и всё.</p>
<h2>Где производная встречается в ЕГЭ</h2>
<ul><li>задача № 7 — график функции или производной;</li><li>задача № 12 — наибольшее и наименьшее значение функции;</li><li>физический смысл — в задачах про движение точки.</li></ul>
<h2>Три правила, которых хватает для экзамена</h2>
<p>Производная степени, суммы и произведения. Таблицу из пяти строк ученики выучивают за два занятия, а дальше всё решается подстановкой.</p>
<aside class="callout">Если производная в точке равна нулю, а слева и справа у неё разные знаки — это точка максимума или минимума. На этом построена почти вся задача № 12.</aside>
HTML;
    }

    private static function draftBody(): string
    {
        return <<<'HTML'
<p>Три месяца — это примерно 24 занятия, если заниматься дважды в неделю. Этого хватает, чтобы уверенно набрать 15–18 баллов, если идти по плану.</p>
<h2>Месяц первый: база</h2>
<p>Повторяем дроби, проценты, степени и уравнения. Каждое занятие заканчивается коротким тестом из пяти заданий первой части.</p>
<h2>Месяц второй: геометрия</h2>
<p>Треугольники, окружности, площади.</p>
HTML;
    }
}
