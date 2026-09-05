<?php

namespace App\Http\Controllers;

use App\Models\LessonType;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
    /** Варианты сортировки списка специалистов (ключ => подпись) */
    public const SORTS = [
        'popular'    => 'По популярности',
        'rating'     => 'По рейтингу',
        'reviews'    => 'По отзывам',
        'price_asc'  => 'Сначала дешевле',
        'price_desc' => 'Сначала дороже',
        'new'        => 'Сначала новые',
    ];

    public const DEFAULT_SORT = 'popular';

    /** Шаг ползунка цены, ₽ */
    public const PRICE_STEP = 50;

    /** Допустимые пороги рейтинга */
    public const RATING_MIN = ['3', '4', '4.5'];

    public function index(Request $request)
    {
        $publishedReviews = fn ($q) => $q
            ->where('is_rejected', false)
            ->whereHas('user', fn ($u) => $u->where('role', User::ROLE_STUDENT));

        $perLessonPrice = fn ($q) => $q
            ->where('payment_type', 'per_lesson')
            ->where('price', '>', 0);

        // «Цена за урок» в карточке и в фильтре — это самое дешёвое занятие с поурочной оплатой.
        // Если выбран формат занятий, минимум считается только по нему.
        $lessonFormats = array_intersect(
            (array) $request->input('lesson_format', []),
            [LessonType::TYPE_INDIVIDUAL, LessonType::TYPE_GROUP]
        );

        // Границы ползунка цены — по реальным ценам активных специалистов, округлённые до шага
        $priceBounds = LessonType::query()
            ->whereIn('user_id', User::isSpecialist()->where('is_active', true)->select('id'))
            ->tap($perLessonPrice)
            ->selectRaw('min(price) as min_price, max(price) as max_price')
            ->first();
        $priceMinBound = (int) floor(($priceBounds->min_price ?? 0) / self::PRICE_STEP) * self::PRICE_STEP;
        $priceMaxBound = (int) ceil(($priceBounds->max_price ?? 0) / self::PRICE_STEP) * self::PRICE_STEP;

        $priceMin = $request->filled('price_min') ? max($priceMinBound, (int) $request->input('price_min')) : null;
        $priceMax = $request->filled('price_max') ? min($priceMaxBound, (int) $request->input('price_max')) : null;
        if ($priceMin !== null && $priceMin <= $priceMinBound) {
            $priceMin = null;
        }
        if ($priceMax !== null && $priceMax >= $priceMaxBound) {
            $priceMax = null;
        }

        $pricedLessons = function ($q) use ($perLessonPrice, $lessonFormats) {
            $perLessonPrice($q);
            if ($lessonFormats) {
                $q->whereIn('type', $lessonFormats);
            }
        };

        $queryBuilder = User::isSpecialist()
            ->where('is_active', true)
            ->withCount([
                'meetingSessions as recent_sessions_count' => fn ($q) => $q->where('started_at', '>=', now()->subDays(30)),
                'meetingSessions as total_sessions_count',
                'receivedReviews as reviews_count' => $publishedReviews,
            ])
            ->withAvg(['receivedReviews as rating_avg' => $publishedReviews], 'rating')
            ->withMin(['lessonTypes as min_price' => $pricedLessons], 'price');

        if ($request->has('user_type')) {
            $types = array_intersect((array) $request->input('user_type'), [User::ROLE_MENTOR, User::ROLE_TUTOR]);
            if ($types) {
                $queryBuilder->whereIn('role', $types);
            }
        }

        if ($request->has('grade')) {
            $grades = (array) $request->input('grade');
            $queryBuilder->where(function ($q) use ($grades) {
                foreach ($grades as $grade) {
                    $q->orWhere('grade', 'LIKE', "%" . $grade . "%");
                }
            });
        }

        if ($request->has('direct')) {
            $directs = (array) $request->input('direct');
            $queryBuilder->whereHas('directs', function ($query) use ($directs) {
                $query->whereIn('directs.id', $directs);
            });
        }

        if ($request->has('subject')) {
            $subjects = (array) $request->input('subject');
            $queryBuilder->whereHas('subjects', function ($query) use ($subjects) {
                $query->whereIn('subjects.id', $subjects);
            });
        }

        if ($lessonFormats) {
            $queryBuilder->whereHas('lessonTypes', fn ($q) => $q->whereIn('type', $lessonFormats));
        }

        if ($priceMin !== null || $priceMax !== null) {
            $minPriceSub = function () use ($pricedLessons) {
                $sub = LessonType::query()
                    ->selectRaw('min(price)')
                    ->whereColumn('lesson_types.user_id', 'users.id');
                $pricedLessons($sub);

                return $sub;
            };

            if ($priceMin !== null) {
                $queryBuilder->where($minPriceSub(), '>=', $priceMin);
            }
            if ($priceMax !== null) {
                $queryBuilder->where($minPriceSub(), '<=', $priceMax);
            }
        }

        $ratingMin = $request->input('rating_min', '');
        $ratingMin = (string) (is_array($ratingMin) ? reset($ratingMin) : $ratingMin);
        if (in_array($ratingMin, self::RATING_MIN, true)) {
            $ratingSub = Review::query()
                ->selectRaw('avg(rating)')
                ->whereColumn('reviews.teacher_id', 'users.id');
            $publishedReviews($ratingSub);

            // Порог подставляем литералом: PDO биндит дробное число строкой,
            // а в SQLite строка всегда «больше» числа и сравнение ложно.
            $queryBuilder->where($ratingSub, '>=', DB::raw($ratingMin));
        }

        $sort = (string) $request->input('sort', self::DEFAULT_SORT);
        if (!array_key_exists($sort, self::SORTS)) {
            $sort = self::DEFAULT_SORT;
        }

        $totalCount = $queryBuilder->count();

        $offset = max(0, (int) $request->input('offset', 0));
        $limit = 20;

        $specialists = $this->applySort($queryBuilder, $sort)
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($request->ajax()) {
            $html = '';
            foreach ($specialists as $specialist) {
                $html .= view('partials.specialist-item', compact('specialist'))->render();
            }

            if ($offset === 0 && $specialists->isEmpty()) {
                $html = view('partials.specialists-empty')->render();
            }

            return response()->json([
                'html' => $html,
                'hasMore' => ($offset + $limit) < $totalCount,
                'totalCount' => $totalCount
            ]);
        }

        return view('index', [
            'specialists'   => $specialists,
            'totalCount'    => $totalCount,
            'sort'          => $sort,
            'sorts'         => self::SORTS,
            'priceBounds'   => [$priceMinBound, $priceMaxBound],
            'priceSelected' => [$priceMin ?? $priceMinBound, $priceMax ?? $priceMaxBound],
            'priceStep'     => self::PRICE_STEP,
            'ratingMin'     => in_array($ratingMin, self::RATING_MIN, true) ? $ratingMin : null,
        ]);
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        // Специалисты без данных (нет отзывов / нет цены за урок) всегда в конце,
        // независимо от направления сортировки.
        return match ($sort) {
            'rating' => $query
                ->orderByRaw('rating_avg is null')
                ->orderByDesc('rating_avg')
                ->orderByDesc('reviews_count'),
            'reviews' => $query
                ->orderByDesc('reviews_count')
                ->orderByDesc('rating_avg'),
            'price_asc' => $query
                ->orderByRaw('min_price is null')
                ->orderBy('min_price')
                ->orderByDesc('recent_sessions_count'),
            'price_desc' => $query
                ->orderByRaw('min_price is null')
                ->orderByDesc('min_price')
                ->orderByDesc('recent_sessions_count'),
            'new' => $query
                ->orderByDesc('created_at'),
            default => $query
                ->orderByDesc('recent_sessions_count')
                ->orderByDesc('total_sessions_count'),
        };
    }
}
