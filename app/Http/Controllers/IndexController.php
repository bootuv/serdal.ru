<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;

class IndexController extends Controller
{
    public function index(Request $request)
    {
        $queryBuilder = User::isSpecialist()
            ->where('is_active', true)
            ->withCount([
                'meetingSessions as recent_sessions_count' => fn ($q) => $q->where('started_at', '>=', now()->subDays(30)),
                'meetingSessions as total_sessions_count',
            ]);

        if ($request->has('user_type')) {
            $types = (array) $request->input('user_type');
            $queryBuilder->whereIn('role', $types);
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

        $totalCount = $queryBuilder->count();

        $offset = $request->input('offset', 0);
        $limit = 20;

        $specialists = $queryBuilder
            ->orderByDesc('recent_sessions_count')
            ->orderByDesc('total_sessions_count')
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($request->ajax()) {
            $html = '';
            foreach ($specialists as $specialist) {
                $html .= view('partials.specialist-item', compact('specialist'))->render();
            }

            return response()->json([
                'html' => $html,
                'hasMore' => ($offset + $limit) < $totalCount,
                'totalCount' => $totalCount
            ]);
        }

        return view('index', compact('specialists'));
    }
}
