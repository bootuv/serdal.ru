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
            ->where('is_active', true);

        if ($request->has('user_type')) {
            $queryBuilder->where('role', $request['user_type']);
        }

        if ($request->has('grade')) {
            $queryBuilder->where('grade', 'LIKE', "%" . $request['grade'] . "%");
        }

        if ($request->has('direct')) {
            $queryBuilder->whereHas('directs', function ($query) use ($request) {
                $query->where('directs.id', $request['direct']);
            });
        }

        if ($request->has('subject')) {
            $queryBuilder->whereHas('subjects', function ($query) use ($request) {
                $query->where('subjects.id', $request['subject']);
            });
        }

        $specialists = $queryBuilder->get();

        return view('index', compact('specialists'));
    }
}
