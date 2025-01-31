<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SpecialistController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->isSpecialist();

        // Применение фильтров
        $query->filterUserType($request->input('user_type'));
        $query->filterDirects($request->input('direct'));
        $query->filterSubjects($request->input('subject'));
        $query->filterGrades($request->input('grade')); // Теперь скоуп исправлен для JSON

        $specialists = $query->with(['directs', 'subjects'])->get();

        return view('index', compact('specialists'));
    }
} 