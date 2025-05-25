<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GroupController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            
            // Fix the pivot query - status is in the pivot table
            $groups = $user->groups()
                ->wherePivot('status', 'accepted')
                ->with('creator')
                ->get();

            return response()->json([
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            Log::error('Group index error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Error retrieving groups',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ]);

            $user = Auth::user();

            $group = Group::create([
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $user->id,
            ]);

            $group->members()->attach($user->id, ['status' => 'accepted']);
            
            Cache::forget("user_groups_{$user->id}");

            return response()->json([
                'message' => 'Group created successfully',
                'group' => $group,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Group store error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error creating group',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Group $group)
    {
        try {
            $this->authorizeView($group);
            $user = Auth::user();
            
            $cacheKey = "group_details_{$group->id}_{$user->id}";
            $groupData = Cache::remember($cacheKey, 300, function() use ($group) {
                $group->load('creator', 'members', 'expenses.paidBy', 'expenses.shares.user');
                return $group;
            });

            return response()->json([
                'group' => $groupData,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 403 Forbidden) without modification
            throw $e;
        } catch (\Exception $e) {
            Log::error('Group show error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error retrieving group',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, Group $group)
    {
        try {
            $this->authorizeUpdate($group);
            $user = Auth::user();

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ]);

            $group->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);
            
            Cache::forget("group_details_{$group->id}_{$user->id}");
            
            $memberIds = $group->members()->pluck('user_id')->toArray();
            foreach ($memberIds as $memberId) {
                Cache::forget("user_groups_{$memberId}");
                Cache::forget("group_balances_{$group->id}_{$memberId}");
            }

            return response()->json([
                'message' => 'Group updated successfully',
                'group' => $group,
            ]);
        } catch (\Exception $e) {
            Log::error('Group update error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error updating group',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Group $group)
    {
        try {
            $this->authorizeDelete($group);
            $user = Auth::user();
            
            $memberIds = $group->members()->pluck('user_id')->toArray();
            
            $group->delete();
            
            Cache::forget("group_details_{$group->id}_{$user->id}");
            
            foreach ($memberIds as $memberId) {
                Cache::forget("user_groups_{$memberId}");
                Cache::forget("group_balances_{$group->id}_{$memberId}");
            }

            return response()->json([
                'message' => 'Group deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Group destroy error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error deleting group',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addMember(Request $request, Group $group)
    {
        try {
            $this->authorizeUpdate($group);

            $request->validate([
                'email' => ['required', 'email', 'exists:users,email'],
            ]);

            $user = User::where('email', $request->email)->first();

            if ($group->members()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'User is already a member of this group',
                ], Response::HTTP_BAD_REQUEST);
            }

            $group->members()->attach($user->id, ['status' => 'pending']);
            
            Cache::forget("user_invitations_{$user->id}");

            return response()->json([
                'message' => 'Member added successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Group addMember error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error adding member to group',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function acceptInvitation(Group $group)
    {
        try {
            $user = Auth::user();
            $membership = $group->members()->where('user_id', $user->id)->first();

            if (!$membership || $membership->pivot->status !== 'pending') {
                return response()->json([
                    'message' => 'Invalid invitation',
                ], Response::HTTP_BAD_REQUEST);
            }

            $group->members()->updateExistingPivot($user->id, ['status' => 'accepted']);
            
            Cache::forget("user_groups_{$user->id}");
            Cache::forget("user_invitations_{$user->id}");
            Cache::forget("group_details_{$group->id}_{$user->id}");

            return response()->json([
                'message' => 'Invitation accepted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Group acceptInvitation error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error accepting invitation',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejectInvitation(Group $group)
    {
        try {
            $user = Auth::user();
            $membership = $group->members()->where('user_id', $user->id)->first();

            if (!$membership || $membership->pivot->status !== 'pending') {
                return response()->json([
                    'message' => 'Invalid invitation',
                ], Response::HTTP_BAD_REQUEST);
            }

            $group->members()->updateExistingPivot($user->id, ['status' => 'rejected']);
            
            Cache::forget("user_invitations_{$user->id}");

            return response()->json([
                'message' => 'Invitation rejected successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Group rejectInvitation error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error rejecting invitation',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function balances(Group $group)
    {
        try {
            $this->authorizeView($group);
            $user = Auth::user();
            
            $cacheKey = "group_balances_{$group->id}_{$user->id}";
            
            return Cache::remember($cacheKey, 900, function() use ($group) {
                $expenses = $group->expenses()
                    ->with(['shares' => function($query) {
                        $query->select('id', 'expense_id', 'user_id', 'share_amount', 'paid_amount', 'is_paid');
                    }])
                    ->with(['paidBy' => function($query) {
                        $query->select('id', 'name');
                    }])
                    ->select(['id', 'group_id', 'paid_by', 'amount', 'date'])
                    ->get();
                
                $members = $group->members()->wherePivot('status', 'accepted')->get();

                $balances = [];
                foreach ($members as $member) {
                    $balances[$member->id] = 0;
                }

                foreach ($expenses as $expense) {
                    $paidBy = $expense->paid_by;
                    $totalAmount = $expense->amount;

                    $balances[$paidBy] += $totalAmount;
                    
                    foreach ($expense->shares as $share) {
                        $paidAmount = $share->paid_amount;
                        
                        if ($paidAmount > 0) {
                            $balances[$paidBy] -= $paidAmount;
                        }
                        
                        $unpaidAmount = $share->share_amount - $paidAmount;
                        if ($unpaidAmount > 0) {
                            $balances[$share->user_id] -= $unpaidAmount;
                        }
                    }
                }

                $balanceDetails = [];
                foreach ($balances as $userId => $balance) {
                    $user = $members->firstWhere('id', $userId);
                    $balanceDetails[] = [
                        'user_id' => $userId,
                        'user_name' => $user->name,
                        'balance' => round($balance, 2),
                    ];
                }

                $simplifiedDebts = $this->calculateSimplifiedDebts($balanceDetails);

                return [
                    'balances' => $balanceDetails,
                    'simplified_debts' => $simplifiedDebts,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Group balances error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error calculating balances',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function calculateSimplifiedDebts($balances)
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $balance) {
            if ($balance['balance'] > 0) {
                $creditors[] = $balance;
            } elseif ($balance['balance'] < 0) {
                $debtors[] = [
                    'user_id' => $balance['user_id'],
                    'user_name' => $balance['user_name'],
                    'balance' => abs($balance['balance']),
                ];
            }
        }

        $debts = [];

        while (!empty($creditors) && !empty($debtors)) {    
            $creditor = $creditors[0];
            $debtor = $debtors[0];

            $amount = min($creditor['balance'], $debtor['balance']);
            
            if ($amount > 0) {
                $debts[] = [
                    'from_user_id' => $debtor['user_id'],
                    'from_user_name' => $debtor['user_name'],
                    'to_user_id' => $creditor['user_id'],
                    'to_user_name' => $creditor['user_name'],
                    'amount' => round($amount, 2),
                ];
            }

            $creditor['balance'] -= $amount;
            $debtor['balance'] -= $amount;

            if ($creditor['balance'] <= 0.01) {
                array_shift($creditors);
            }

            if ($debtor['balance'] <= 0.01) {
                array_shift($debtors);
            }
        }

        return $debts;
    }

    private function authorizeView(Group $group)
    {
        $user = Auth::user();
        $isMember = $group->members()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'accepted')
            ->exists();

        if (!$isMember) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view this group');
        }
    }

    private function authorizeUpdate(Group $group)
    {
        $user = Auth::user();
        if ($group->created_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to update this group');
        }
    }

    private function authorizeDelete(Group $group)
    {
        $user = Auth::user();
        if ($group->created_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to delete this group');
        }
    }
}
