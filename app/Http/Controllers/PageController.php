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
        $reviews = Review::with('user')
            ->where('is_rejected', false)
            ->get();

        return view('reviews', compact('reviews'));
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
