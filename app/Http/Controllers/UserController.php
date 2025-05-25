<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        return $this->handleRequest(function () use ($request) {
            $validatedData = $request->validate([
                'query' => ['required', 'string', 'min:2', 'max:255'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $query = trim($validatedData['query']);
            $limit = $validatedData['limit'] ?? 10;
            $currentUser = Auth::user();

            try {
                $users = User::where('id', '!=', $currentUser->id)
                    ->where(function ($queryBuilder) use ($query) {
                        $queryBuilder->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->select('id', 'name', 'email')
                    ->limit($limit)
                    ->get();

                return $this->successResponse([
                    'users' => $users,
                    'query' => $query,
                    'total_found' => $users->count(),
                ], 'Users found successfully');

            } catch (Throwable $e) {
                Log::error('Error searching users', [
                    'query' => $query,
                    'user_id' => $currentUser->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'Failed to search users',
                    500,
                    null,
                    'USER_SEARCH_ERROR'
                );
            }
        });
    }

    public function invitations(): JsonResponse
    {
        return $this->handleRequest(function () {
            $user = Auth::user();
            
            try {
                $invitations = $user->groups()
                    ->wherePivot('status', 'pending')
                    ->with(['creator' => function ($query) {
                        $query->select('id', 'name', 'email');
                    }])
                    ->withCount(['members' => function ($query) {
                        $query->where('status', 'accepted');
                    }])
                    ->select('groups.id', 'groups.name', 'groups.description', 'groups.created_at', 'groups.created_by')
                    ->get();

                return $this->successResponse([
                    'invitations' => $invitations,
                    'total_pending' => $invitations->count(),
                ], 'Invitations retrieved successfully');

            } catch (Throwable $e) {
                Log::error('Error retrieving user invitations', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'Failed to retrieve invitations',
                    500,
                    null,
                    'INVITATIONS_RETRIEVAL_ERROR'
                );
            }
        });
    }

    public function profile(): JsonResponse
    {
        return $this->handleRequest(function () {
            $user = Auth::user();

            try {
                // Get user statistics
                $groupsCount = $user->groups()->wherePivot('status', 'accepted')->count();
                $pendingInvitationsCount = $user->groups()->wherePivot('status', 'pending')->count();
                
                // Get expenses statistics
                $totalExpensesCreated = $user->expensesPaidBy()->count();
                $totalExpensesShared = $user->expenseShares()->count();
                
                // Get total amount statistics
                $totalAmountPaid = $user->expensesPaidBy()->sum('amount');
                $totalAmountOwed = $user->expenseShares()
                    ->where('is_paid', false)
                    ->sum('share_amount');

                $profileData = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'created_at' => $user->created_at,
                    ],
                    'statistics' => [
                        'groups_count' => $groupsCount,
                        'pending_invitations_count' => $pendingInvitationsCount,
                        'expenses_created_count' => $totalExpensesCreated,
                        'expenses_shared_count' => $totalExpensesShared,
                        'total_amount_paid' => round($totalAmountPaid, 2),
                        'total_amount_owed' => round($totalAmountOwed, 2),
                    ],
                ];

                return $this->successResponse($profileData, 'Profile retrieved successfully');

            } catch (Throwable $e) {
                Log::error('Error retrieving user profile', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'Failed to retrieve profile information',
                    500,
                    null,
                    'PROFILE_RETRIEVAL_ERROR'
                );
            }
        });
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return $this->handleRequest(function () use ($request) {
            $user = Auth::user();

            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255', 'min:2'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            ]);

            try {
                $user->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                ]);

                Log::info('User profile updated', [
                    'user_id' => $user->id,
                    'changes' => array_keys($validatedData),
                ]);

                return $this->successResponse([
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'updated_at' => $user->updated_at,
                    ],
                ], 'Profile updated successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'profile update');
            }
        });
    }

    public function dashboard(): JsonResponse
    {
        return $this->handleRequest(function () {
            $user = Auth::user();

            try {
                // Get recent groups
                $recentGroups = $user->groups()
                    ->wherePivot('status', 'accepted')
                    ->with(['creator' => function ($query) {
                        $query->select('id', 'name');
                    }])
                    ->withCount(['members' => function ($query) {
                        $query->where('status', 'accepted');
                    }])
                    ->orderBy('groups.updated_at', 'desc')
                    ->limit(5)
                    ->get(['groups.id', 'groups.name', 'groups.description', 'groups.created_by', 'groups.updated_at']);

                // Get recent expenses user was involved in
                $recentExpenses = $user->expenseShares()
                    ->with([
                        'expense' => function ($query) {
                            $query->with(['group:id,name', 'paidBy:id,name']);
                        }
                    ])
                    ->orderBy('expenses.created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($share) {
                        return [
                            'id' => $share->expense->id,
                            'description' => $share->expense->description,
                            'amount' => $share->expense->amount,
                            'share_amount' => $share->share_amount,
                            'paid_amount' => $share->paid_amount,
                            'is_paid' => $share->is_paid,
                            'date' => $share->expense->date,
                            'group' => $share->expense->group,
                            'paid_by' => $share->expense->paidBy,
                        ];
                    });

                // Get pending invitations
                $pendingInvitations = $user->groups()
                    ->wherePivot('status', 'pending')
                    ->with(['creator:id,name'])
                    ->get(['groups.id', 'groups.name', 'groups.description', 'groups.created_by']);

                // Calculate summary statistics
                $totalOwed = $user->expenseShares()
                    ->where('is_paid', false)
                    ->sum('share_amount');

                $totalOwedToUser = $user->expensesPaidBy()
                    ->whereHas('shares', function ($query) {
                        $query->where('is_paid', false);
                    })
                    ->with('shares')
                    ->get()
                    ->sum(function ($expense) {
                        return $expense->shares->where('is_paid', false)->sum('share_amount');
                    });

                $dashboardData = [
                    'summary' => [
                        'total_groups' => $recentGroups->count(),
                        'pending_invitations' => $pendingInvitations->count(),
                        'total_owed_by_user' => round($totalOwed, 2),
                        'total_owed_to_user' => round($totalOwedToUser, 2),
                    ],
                    'recent_groups' => $recentGroups,
                    'recent_expenses' => $recentExpenses,
                    'pending_invitations' => $pendingInvitations,
                ];

                return $this->successResponse($dashboardData, 'Dashboard data retrieved successfully');

            } catch (Throwable $e) {
                Log::error('Error retrieving dashboard data', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'Failed to retrieve dashboard data',
                    500,
                    null,
                    'DASHBOARD_ERROR'
                );
            }
        });
    }
}
