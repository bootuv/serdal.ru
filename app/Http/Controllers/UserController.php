<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete(); // Это также удалит связанные отзывы благодаря каскаду

        return redirect()->route('users.index')->with('success', 'Пользователь успешно удален.');
    }
} 