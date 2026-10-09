<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LessonType;
use App\Models\Review;
use App\Support\OfferSettings;
class PageController extends Controller
{
    /** Вкладки /reviews: все · ученики (об учителях) · учителя (о платформе, после проверки). */
    private const REVIEW_ROLES = ['student', 'tutor'];

    /** Отзывы для /reviews по вкладке; teacher — отзывы об одном учителе (страница учителя). */
    private function publicReviews(?string $role, ?int $teacherId = null)
    {
        $query = Review::with(['user', 'teacher'])->public();

        return match (true) {
            (bool) $teacherId => $query->where('teacher_id', $teacherId),
            $role === 'student' => $query->aboutTeachers(),
            $role === 'tutor' => $query->platform(),
            default => $query,
        };
    }

    public function reviewsPage(Request $request)
    {
        $role = in_array($request->query('role'), self::REVIEW_ROLES, true) ? $request->query('role') : null;
        $query = $this->publicReviews($role);

        $totalCount = (clone $query)->count();
        $reviews = $query->latest()->orderByDesc('id')->take(20)->get();
        $hasMore = $totalCount > 20;

        return view('reviews', compact('reviews', 'hasMore', 'totalCount', 'role'));
    }

    public function loadMoreReviews(Request $request)
    {
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = 20;
        $teacherId = $request->integer('teacher') ?: null;
        $role = in_array($request->input('role'), self::REVIEW_ROLES, true) ? $request->input('role') : null;

        $query = $this->publicReviews($role, $teacherId);
        $totalCount = (clone $query)->count();

        $reviews = $query
            ->latest()
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        $hasMore = ($offset + $limit) < $totalCount;

        $html = '';
        foreach ($reviews as $review) {
            $html .= view('partials.review-item', [
                'review' => $review,
                'hideTeacherMention' => (bool) $teacherId,
            ])->render();
        }

        return response()->json([
            'html' => $html,
            'hasMore' => $hasMore,
        ]);
    }

    public function tutorPage($username)
    {
        $user = User::whereUsername($username)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->with(['directs', 'subjects', 'lessonTypes'])
            ->firstOrFail();

        $lessonTypeIndividual = $user->lessonTypes->where('type', LessonType::TYPE_INDIVIDUAL)->first();
        $lessonTypeGroup = $user->lessonTypes->where('type', LessonType::TYPE_GROUP)->first();

        $reviewsQuery = Review::with('user')
            ->where('teacher_id', $user->id)
            ->where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT));

        $reviewsTotal = (clone $reviewsQuery)->count();
        $ratingAvg = $reviewsTotal > 0 ? (float) (clone $reviewsQuery)->avg('rating') : null;

        $reviews = $reviewsQuery
            ->latest()
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $reviewsHasMore = $reviewsTotal > 20;

        // Статьи учителя в блоге: последние три и ссылка на все (блог → автор)
        $blogQuery = $user->blogPosts()->published();
        $blogTotal = (clone $blogQuery)->count();
        $blogPosts = $blogTotal ? $blogQuery->with('author.subjects')->latest('published_at')->take(4)->get() : collect();

        return view('tutor', compact('user', 'lessonTypeIndividual', 'lessonTypeGroup', 'reviews', 'reviewsHasMore', 'reviewsTotal', 'ratingAvg', 'blogPosts', 'blogTotal'));
    }

    public function aboutPage()
    {
        return view('about');
    }

    public function privacyPage()
    {
        return view('privacy');
    }

    public function termsPage()
    {
        return view('terms');
    }

    public function tariffsPage()
    {
        $tariffs = \App\Models\Tariff::active()->get();

        return view('tariffs', [
            'tariffs' => $tariffs,
            'b2b' => OfferSettings::b2b(),
            'offer' => OfferSettings::offer(),
            'legal' => OfferSettings::legal(),
            'platform' => OfferSettings::platform(),
            'periodDays' => $tariffs->pluck('period_days')->unique()->values()->all(),
            'extraLessonPrice' => \App\Services\SubscriptionService::extraLessonPrice(),
            'extraLessonsMax' => \App\Services\SubscriptionService::extraLessonsMax(),
        ]);
    }

    public function offerPage()
    {
        $tariffs = \App\Models\Tariff::active()->get();

        return view('offer', [
            'tariffs' => $tariffs,
            'b2b' => OfferSettings::b2b(),
            'offer' => OfferSettings::offer(),
            'legal' => OfferSettings::legal(),
            'platform' => OfferSettings::platform(),
            'periodDays' => $tariffs->pluck('period_days')->unique()->values()->all(),
            'extraLessonPrice' => \App\Services\SubscriptionService::extraLessonPrice(),
            'extraLessonsMax' => \App\Services\SubscriptionService::extraLessonsMax(),
        ]);
    }
}
