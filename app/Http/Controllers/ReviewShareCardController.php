<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use App\Services\ReviewShareCardGenerator;

class ReviewShareCardController extends Controller
{
    public function __invoke(Review $review, ReviewShareCardGenerator $generator)
    {
        $user = auth()->user();

        abort_unless($user->role === User::ROLE_ADMIN || $review->teacher_id === $user->id, 403);
        abort_if($review->is_rejected, 404);

        return response($generator->generate($review), 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="serdal-review-' . $review->id . '.jpg"',
        ]);
    }
}
