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

        $reviews = Review::with(['user', 'teacher'])
            ->where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT))
            ->latest()
            ->skip($offset)
            ->take($limit)
            ->get();

        $totalCount = Review::where('is_rejected', false)
            ->whereHas('user', fn($q) => $q->where('role', User::ROLE_STUDENT))
            ->count();

        $hasMore = ($offset + $limit) < $totalCount;

        $html = '';
        foreach ($reviews as $review) {
            $html .= view('partials.review-item', compact('review'))->render();
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

        return view('tutor', compact('user', 'lessonTypeIndividual', 'lessonTypeGroup'));
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
