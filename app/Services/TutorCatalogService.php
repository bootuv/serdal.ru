<?php

namespace App\Services;

use App\Models\User;
use App\Support\Seo;
use App\Support\SeoPhrases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Посадочные страницы каталога для поисковиков: «Репетитор по математике» (/repetitory/matematika),
 * «Подготовка к ЕГЭ» (/napravleniya/ege) и их сочетания (/repetitory/matematika/ege).
 * Страница существует, только пока в ней есть хотя бы один учитель; сочетание с одним учителем
 * открыто людям, но закрыто от индексации — такие страницы поисковики считают малополезными.
 */
class TutorCatalogService
{
    public const CACHE_KEY = 'seo.catalog';

    private const CACHE_TTL = 3600;

    /** Сколько учителей должно быть в сочетании «предмет + направление», чтобы открыть его для поиска. */
    public const MIN_TUTORS_TO_INDEX_COMBO = 2;

    /** Учителя с публичной страницей: активные, не заблокированные, с адресом. */
    public function publicTutorsQuery(): Builder
    {
        return User::isSpecialist()
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->whereNotNull('username');
    }

    /**
     * Все посадочные страницы: предметы, направления и сочетания с числом учителей.
     *
     * @return array{
     *     subjects: array<string, array{id:int, name:string, slug:string, heading:string, url:string, count:int}>,
     *     directs: array<string, array{id:int, name:string, slug:string, heading:string, url:string, count:int}>,
     *     combos: array<string, array{subject:string, direct:string, heading:string, url:string, count:int, indexable:bool}>,
     *     stats: array,
     *     updated_at: ?string,
     * }
     */
    public function catalog(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->buildCatalog());
    }

    public function subject(string $slug): ?array
    {
        return $this->catalog()['subjects'][$slug] ?? null;
    }

    public function direct(string $slug): ?array
    {
        return $this->catalog()['directs'][$slug] ?? null;
    }

    public function combo(string $subjectSlug, string $directSlug): ?array
    {
        return $this->catalog()['combos'][$subjectSlug . '/' . $directSlug] ?? null;
    }

    /** Предметы с наибольшим числом учителей — для подвала и главной. */
    public function topSubjects(int $limit): array
    {
        return collect($this->catalog()['subjects'])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Сочетания одного предмета (или одного направления) — ссылки «ЕГЭ · ОГЭ · олимпиады». */
    public function combosFor(?string $subjectSlug = null, ?string $directSlug = null): array
    {
        return collect($this->catalog()['combos'])
            ->filter(fn ($combo) => ($subjectSlug === null || $combo['subject'] === $subjectSlug)
                && ($directSlug === null || $combo['direct'] === $directSlug))
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Учителя страницы с рейтингом, числом отзывов и ценами — в порядке популярности, как в каталоге на главной.
     *
     * @return Collection<int, User>
     */
    public function tutors(?int $subjectId = null, ?int $directId = null): Collection
    {
        $publishedReviews = fn ($q) => $q
            ->where('is_rejected', false)
            ->whereHas('user', fn ($u) => $u->where('role', User::ROLE_STUDENT));

        return $this->publicTutorsQuery()
            ->when($subjectId, fn ($q) => $q->whereHas('subjects', fn ($s) => $s->whereKey($subjectId)))
            ->when($directId, fn ($q) => $q->whereHas('directs', fn ($d) => $d->whereKey($directId)))
            ->with(['directs', 'subjects', 'lessonTypes'])
            ->withCount([
                'meetingSessions as recent_sessions_count' => fn ($q) => $q->where('started_at', '>=', now()->subDays(30)),
                'meetingSessions as total_sessions_count',
                'receivedReviews as reviews_count' => $publishedReviews,
            ])
            ->withAvg(['receivedReviews as rating_avg' => $publishedReviews], 'rating')
            ->orderByDesc('recent_sessions_count')
            ->orderByDesc('total_sessions_count')
            ->orderBy('id')
            ->get();
    }

    /**
     * Цифры для текста страницы: сколько учителей, цены за занятие, средняя оценка и классы.
     *
     * @param  Collection<int, User>  $tutors
     * @return array{count:int, price_min:?int, price_max:?int, price_avg:?int, rating:?float, reviews:int, grades:string}
     */
    public function stats(Collection $tutors): array
    {
        $prices = $tutors
            ->map(fn (User $tutor) => $tutor->cheapestLesson()?->pricePerLesson())
            ->filter()
            ->values();

        $reviews = (int) $tutors->sum('reviews_count');
        $ratingSum = $tutors->sum(fn (User $tutor) => (float) $tutor->rating_avg * (int) $tutor->reviews_count);

        // Классы всех учителей раздела — тем же форматом, что в карточке учителя («1–11 классы, взрослые»)
        $grades = $tutors->pluck('grade')->filter()->flatten()->unique()->values()->all();

        return [
            'count' => $tutors->count(),
            'price_min' => $prices->isNotEmpty() ? (int) $prices->min() : null,
            'price_max' => $prices->isNotEmpty() ? (int) $prices->max() : null,
            'price_avg' => $prices->isNotEmpty() ? (int) (round($prices->avg() / 50) * 50) : null,
            'rating' => $reviews > 0 ? round($ratingSum / $reviews, 1) : null,
            'reviews' => $reviews,
            'grades' => $grades ? (new User())->forceFill(['grade' => $grades])->displayGrade : '',
        ];
    }

    /**
     * Частые вопросы с ответами на цифрах раздела. Их же видят поисковики и ИИ-ассистенты (FAQPage).
     *
     * @return array<int, array{question:string, answer:string}>
     */
    public function faq(array $stats): array
    {
        $faq = [];

        if ($stats['price_min'] !== null) {
            $range = $stats['price_min'] === $stats['price_max']
                ? $this->rub($stats['price_min'])
                : 'от ' . number_format($stats['price_min'], 0, ',', ' ') . ' до ' . $this->rub($stats['price_max'])
                    . ', в среднем — ' . $this->rub($stats['price_avg']);

            $faq[] = [
                'question' => 'Сколько стоят занятия?',
                'answer' => 'Цену назначает сам учитель. Сейчас занятие здесь стоит ' . $range . '. '
                    . 'Если учитель берёт оплату за месяц, в карточке указана примерная цена одного занятия.',
            ];
        }

        $faq[] = [
            'question' => 'Как проходят онлайн-занятия?',
            'answer' => 'Занятие идёт в браузере на компьютере, планшете или телефоне — ничего устанавливать не нужно. '
                . 'В классе есть видеосвязь, общая интерактивная доска и демонстрация экрана. '
                . 'Занятие можно записать и потом пересмотреть в личном кабинете.',
        ];

        $faq[] = [
            'question' => 'Как выбрать репетитора?',
            'answer' => 'Сравните учителей по отзывам учеников, оценке, цене и классам, с которыми они работают. '
                . 'На странице учителя есть рассказ о себе, форматы и цены занятий и способы связи.',
        ];

        $faq[] = [
            'question' => 'Как записаться на занятие?',
            'answer' => 'Откройте страницу учителя и свяжитесь с ним в Telegram, WhatsApp или по телефону. '
                . 'Когда договоритесь, учитель пригласит вас на платформу, и занятия появятся в расписании в личном кабинете.',
        ];

        $faq[] = [
            'question' => 'Нужно ли ученику платить за платформу?',
            'answer' => 'Нет, для ученика платформа бесплатна. Занятия оплачиваются учителю напрямую, как договоритесь, '
                . 'а в кабинете видно, какие занятия уже оплачены.',
        ];

        return $faq;
    }

    /** «от 500 ₽ за занятие · оценка 4,9 по 37 отзывам · 5–11 классы» — строка фактов под заголовком. */
    public function factsLine(array $stats): array
    {
        return array_values(array_filter([
            plural_ru($stats['count'], 'репетитор', 'репетитора', 'репетиторов'),
            $stats['price_min'] !== null ? 'от ' . $this->rub($stats['price_min']) . ' за занятие' : null,
            $stats['rating'] !== null
                ? 'оценка ' . number_format($stats['rating'], 1, ',', '') . ' по ' . plural_ru($stats['reviews'], 'отзыву', 'отзывам', 'отзывам')
                : null,
            $stats['grades'] !== '' ? mb_strtolower(mb_substr($stats['grades'], 0, 1)) . mb_substr($stats['grades'], 1) : null,
        ]));
    }

    private function rub(int $value): string
    {
        return number_format($value, 0, ',', ' ') . ' ₽';
    }

    private function buildCatalog(): array
    {
        $tutors = $this->publicTutorsQuery()
            ->with(['subjects:id,name', 'directs:id,name'])
            ->get(['id', 'updated_at']);

        $subjects = [];
        $directs = [];
        $combos = [];

        $entry = function (array &$list, $model, string $heading, string $route) {
            $slug = SeoPhrases::slug($model->name);
            if ($slug === '') {
                return null;
            }
            // Два названия с одним адресом — берём первое, второе останется только в каталоге на главной
            if (isset($list[$slug]) && $list[$slug]['id'] !== $model->id) {
                return null;
            }
            $list[$slug] ??= [
                'id' => $model->id,
                'name' => trim($model->name),
                'slug' => $slug,
                'heading' => $heading,
                'url' => Seo::url(route($route, $slug, false)),
                'count' => 0,
            ];
            $list[$slug]['count']++;

            return $slug;
        };

        foreach ($tutors as $tutor) {
            $subjectSlugs = [];
            foreach ($tutor->subjects as $subject) {
                if ($slug = $entry($subjects, $subject, SeoPhrases::subjectHeading($subject->name), 'catalog.subject')) {
                    $subjectSlugs[$slug] = $subject->name;
                }
            }

            foreach ($tutor->directs as $direct) {
                $directSlug = $entry($directs, $direct, SeoPhrases::directHeading($direct->name), 'catalog.direct');
                if (!$directSlug) {
                    continue;
                }

                foreach ($subjectSlugs as $subjectSlug => $subjectName) {
                    if (!SeoPhrases::combinable($direct->name, $subjectName)) {
                        continue;
                    }
                    $key = $subjectSlug . '/' . $directSlug;
                    $combos[$key] ??= [
                        'subject' => $subjectSlug,
                        'direct' => $directSlug,
                        'heading' => SeoPhrases::comboHeading($subjectName, $direct->name),
                        'url' => Seo::url(route('catalog.combo', [$subjectSlug, $directSlug], false)),
                        'count' => 0,
                        'indexable' => false,
                    ];
                    $combos[$key]['count']++;
                }
            }
        }

        foreach ($combos as $key => $combo) {
            $combos[$key]['indexable'] = $combo['count'] >= self::MIN_TUTORS_TO_INDEX_COMBO;
        }

        return [
            'subjects' => $subjects,
            'directs' => $directs,
            'combos' => $combos,
            'stats' => $this->stats($this->tutors()),
            'updated_at' => $tutors->max('updated_at')?->toDateString(),
        ];
    }
}
