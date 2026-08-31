<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LessonType;
use App\Models\Review;
class PageController extends Controller
{
    public function reviewsPage()
    {
        $reviews = Review::with(['user', 'teacher'])
            ->where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT))
            ->latest()
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $totalCount = Review::where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT))
            ->count();

        $hasMore = $totalCount > 20;

        return view('reviews', compact('reviews', 'hasMore', 'totalCount'));
    }

    public function loadMoreReviews(Request $request)
    {
        $offset = $request->input('offset', 0);
        $limit = 20;
        $teacherId = $request->input('teacher');

        $query = Review::with(['user', 'teacher'])
            ->where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT))
            ->when($teacherId, fn($q) => $q->where('teacher_id', $teacherId));

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
            ->with(['directs', 'subjects', 'lessonTypes'])
            ->firstOrFail();

        $lessonTypeIndividual = $user->lessonTypes->where('type', LessonType::TYPE_INDIVIDUAL)->first();
        $lessonTypeGroup = $user->lessonTypes->where('type', LessonType::TYPE_GROUP)->first();

        $reviewsQuery = Review::with('user')
            ->where('teacher_id', $user->id)
            ->where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT));

        $reviewsTotal = (clone $reviewsQuery)->count();

        $reviews = $reviewsQuery
            ->latest()
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $reviewsHasMore = $reviewsTotal > 20;

        return view('tutor', compact('user', 'lessonTypeIndividual', 'lessonTypeGroup', 'reviews', 'reviewsHasMore'));
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

        // Блок B2B — настраивается в админке (Настройки → B2B-блок)
        $b2bDefaults = [
            'b2b_enabled' => '1',
            'b2b_title' => 'Для образовательных центров (B2B)',
            'b2b_description' => 'Пакет для онлайн-школ и образовательных центров: white-label, администрирование и поддержка с SLA.',
            'b2b_price_label' => 'от 14 900 ₽',
            'b2b_price_note' => '5 рабочих мест включено',
            'b2b_features' => json_encode([
                '5 рабочих мест преподавателей включено (дополнительное место — 1 900 ₽/мес)',
                'White-label: платформа под брендом вашего центра',
                'Административная панель для управления преподавателями и учениками',
                'Приоритетная поддержка и SLA',
                'Обучение и онбординг команды',
            ], JSON_UNESCAPED_UNICODE),
            'b2b_email' => 'info@serdal.ru',
        ];
        $b2bSettings = \App\Models\Setting::whereIn('key', array_keys($b2bDefaults))
            ->pluck('value', 'key')
            ->filter(fn($value) => $value !== null)
            ->all();
        $b2bSettings += $b2bDefaults;

        $b2b = [
            'enabled' => $b2bSettings['b2b_enabled'] === '1',
            'title' => $b2bSettings['b2b_title'],
            'description' => $b2bSettings['b2b_description'],
            'price_label' => $b2bSettings['b2b_price_label'],
            'price_note' => $b2bSettings['b2b_price_note'],
            'features' => json_decode($b2bSettings['b2b_features'], true) ?: [],
            'email' => $b2bSettings['b2b_email'],
        ];

        return view('tariffs', compact('tariffs', 'b2b'));
    }

    public function offerPage()
    {
        $legal = \App\Models\Setting::whereIn('key', [
            'legal_name',
            'legal_inn',
            'legal_ogrn',
            'legal_address',
            'legal_email',
            'legal_phone',
        ])->pluck('value', 'key');

        $tariffs = \App\Models\Tariff::active()->get();

        return view('offer', compact('legal', 'tariffs'));
    }
}
