<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;

class IndexController extends Controller
{
    public function index(Request $request)
    {
        $queryBuilder = User::isSpecialist();

        if ($request->has('user_type')) {
            $queryBuilder->where('role', $request['user_type']);
        }

        if ($request->has('grade')) {
            switch (Str::lower($request['grade'])) {
                case 'дошкольники':
                    $queryBuilder->where('grade->preschool', true);
                    break;
                case 'взрослые':
                    $queryBuilder->where('grade->adults', true);
                    break;
                default:
                    $queryBuilder->where('grade->school', 'LIKE', "%" . $request['grade'] . "%");
            }
        }

        $specialists = $queryBuilder->get();

        return view('index', compact('specialists'));
    }
}
