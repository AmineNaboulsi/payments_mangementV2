<?php

namespace App\Http\Controllers;

use App\Helpers\RedisCacheHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => ['required', 'string', 'min:3'],
        ]);

        $query = $request->input('query');
        $currentUser = Auth::user();

        $users = User::where('id', '!=', $currentUser->id)
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'email')
            ->limit(10)
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function invitations()
    {
        $user = Auth::user();
        
        $cacheKey = "user_invitations_{$user->id}";
        $invitations = RedisCacheHelper::remember($cacheKey, 5, function() use ($user) {
            return $user->groups()
                ->wherePivot('status', 'pending')
                ->with('creator')
                ->get();
        });

        return response()->json([
            'invitations' => $invitations,
        ]);
    }
}
