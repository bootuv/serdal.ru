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

    public function privacyPage()
    {
        return view('privacy');
    }

    public function termsPage()
    {
        return view('terms');
    }
}
