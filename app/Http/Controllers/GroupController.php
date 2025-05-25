<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->handleRequest(function () {
            $user = Auth::user();
            
            $groups = $user->groups()
                ->wherePivot('status', 'accepted')
                ->with('creator')
                ->withCount('members')
                ->get();

            return $this->successResponse([
                'groups' => $groups,
            ], 'Groups retrieved successfully');
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->handleRequest(function () use ($request) {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]);

            $user = Auth::user();

            DB::beginTransaction();
            try {
                $group = Group::create([
                    'name' => $validatedData['name'],
                    'description' => $validatedData['description'],
                    'created_by' => $user->id,
                ]);

                $group->members()->attach($user->id, ['status' => 'accepted']);

                DB::commit();

                $group->load('creator');

                Log::info('Group created successfully', [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'user_id' => $user->id,
                ]);

                return $this->successResponse([
                    'group' => $group,
                ], 'Group created successfully', Response::HTTP_CREATED);

            } catch (Throwable $e) {
                DB::rollBack();
                return $this->handleDatabaseError($e, 'group creation');
            }
        });
    }

    public function show(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $this->authorizeView($group);
            
            $group->load([
                'creator',
                'members' => function ($query) {
                    $query->wherePivot('status', 'accepted');
                },
                'expenses.paidBy',
                'expenses.shares.user'
            ]);

            return $this->successResponse([
                'group' => $group,
            ], 'Group retrieved successfully');
        });
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group) {
            $this->authorizeUpdate($group);

            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]);

            try {
                $group->update([
                    'name' => $validatedData['name'],
                    'description' => $validatedData['description'],
                ]);

                Log::info('Group updated successfully', [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'group' => $group->fresh(),
                ], 'Group updated successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'group update');
            }
        });
    }

    public function destroy(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $this->authorizeDelete($group);
            
            // Check if group has any expenses
            $hasExpenses = $group->expenses()->exists();
            if ($hasExpenses) {
                return $this->badRequestResponse('Cannot delete group that contains expenses');
            }

            try {
                $groupId = $group->id;
                $groupName = $group->name;
                
                $group->delete();

                Log::info('Group deleted successfully', [
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse(null, 'Group deleted successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'group deletion');
            }
        });
    }

    public function addMember(Request $request, Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group) {
            $this->authorizeUpdate($group);

            $validatedData = $request->validate([
                'email' => ['required', 'email', 'exists:users,email'],
            ]);

            $user = User::where('email', $validatedData['email'])->first();

            if (!$user) {
                return $this->notFoundResponse('User with this email');
            }

            // Check if user is already a member (any status)
            $existingMembership = $group->members()->where('user_id', $user->id)->first();
            
            if ($existingMembership) {
                $status = $existingMembership->pivot->status;
                
                if ($status === 'accepted') {
                    return $this->badRequestResponse('User is already a member of this group');
                } elseif ($status === 'pending') {
                    return $this->badRequestResponse('User already has a pending invitation to this group');
                } elseif ($status === 'rejected') {
                    // Re-invite the user by updating status to pending
                    try {
                        $group->members()->updateExistingPivot($user->id, ['status' => 'pending']);
                        
                        Log::info('User re-invited to group', [
                            'group_id' => $group->id,
                            'invited_user_id' => $user->id,
                            'inviter_user_id' => Auth::id(),
                        ]);

                        return $this->successResponse([
                            'user' => $user->only(['id', 'name', 'email']),
                        ], 'User re-invited to group successfully');

                    } catch (Throwable $e) {
                        return $this->handleDatabaseError($e, 'user re-invitation');
                    }
                }
            }

            // Add new member with pending status
            try {
                $group->members()->attach($user->id, ['status' => 'pending']);

                Log::info('User invited to group', [
                    'group_id' => $group->id,
                    'invited_user_id' => $user->id,
                    'inviter_user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'user' => $user->only(['id', 'name', 'email']),
                ], 'User invited to group successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'user invitation');
            }
        });
    }

    public function acceptInvitation(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $user = Auth::user();
            $membership = $group->members()->where('user_id', $user->id)->first();

            if (!$membership) {
                return $this->notFoundResponse('Group invitation');
            }

            if ($membership->pivot->status === 'accepted') {
                return $this->badRequestResponse('You are already a member of this group');
            }

            if ($membership->pivot->status !== 'pending') {
                return $this->badRequestResponse('No pending invitation found for this group');
            }

            try {
                $group->members()->updateExistingPivot($user->id, ['status' => 'accepted']);

                Log::info('Group invitation accepted', [
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                ]);

                return $this->successResponse([
                    'group' => $group->load('creator'),
                ], 'Invitation accepted successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'invitation acceptance');
            }
        });
    }

    public function rejectInvitation(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $user = Auth::user();
            $membership = $group->members()->where('user_id', $user->id)->first();

            if (!$membership) {
                return $this->notFoundResponse('Group invitation');
            }

            if ($membership->pivot->status !== 'pending') {
                return $this->badRequestResponse('No pending invitation found for this group');
            }

            try {
                $group->members()->updateExistingPivot($user->id, ['status' => 'rejected']);

                Log::info('Group invitation rejected', [
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                ]);

                return $this->successResponse(null, 'Invitation rejected successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'invitation rejection');
            }
        });
    }

    public function removeMember(Request $request, Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group) {
            $this->authorizeUpdate($group);

            $validatedData = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);

            $userToRemove = User::find($validatedData['user_id']);
            $currentUser = Auth::user();

            // Cannot remove yourself
            if ($userToRemove->id === $currentUser->id) {
                return $this->badRequestResponse('You cannot remove yourself from the group. Use leave group instead.');
            }

            // Check if user is actually a member
            $membership = $group->members()->where('user_id', $userToRemove->id)->first();
            if (!$membership) {
                return $this->notFoundResponse('User membership in this group');
            }

            // Check if user has unpaid expenses
            $hasUnpaidExpenses = $group->expenses()
                ->whereHas('shares', function ($query) use ($userToRemove) {
                    $query->where('user_id', $userToRemove->id)
                          ->where('is_paid', false);
                })
                ->exists();

            if ($hasUnpaidExpenses) {
                return $this->badRequestResponse('Cannot remove user who has unpaid expenses in this group');
            }

            try {
                $group->members()->detach($userToRemove->id);

                Log::info('Member removed from group', [
                    'group_id' => $group->id,
                    'removed_user_id' => $userToRemove->id,
                    'remover_user_id' => $currentUser->id,
                ]);

                return $this->successResponse(null, 'Member removed from group successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'member removal');
            }
        });
    }

    public function leaveGroup(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $user = Auth::user();

            // Check if user is the creator
            if ($group->created_by === $user->id) {
                return $this->badRequestResponse('Group creator cannot leave the group. Transfer ownership or delete the group instead.');
            }

            // Check if user is actually a member
            $membership = $group->members()->where('user_id', $user->id)->first();
            if (!$membership) {
                return $this->notFoundResponse('Your membership in this group');
            }

            // Check if user has unpaid expenses
            $hasUnpaidExpenses = $group->expenses()
                ->whereHas('shares', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('is_paid', false);
                })
                ->exists();

            if ($hasUnpaidExpenses) {
                return $this->badRequestResponse('You cannot leave the group while you have unpaid expenses');
            }

            try {
                $group->members()->detach($user->id);

                Log::info('User left group', [
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                ]);

                return $this->successResponse(null, 'Left group successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'leaving group');
            }
        });
    }

    public function balances(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $this->authorizeView($group);
            
            try {
                $expenses = $group->expenses()
                    ->with(['shares' => function($query) {
                        $query->select('id', 'expense_id', 'user_id', 'share_amount', 'paid_amount', 'is_paid');
                    }])
                    ->with(['paidBy' => function($query) {
                        $query->select('id', 'name');
                    }])
                    ->select(['id', 'group_id', 'paid_by', 'amount', 'date'])
                    ->get();
                
                $members = $group->members()
                    ->wherePivot('status', 'accepted')
                    ->select('users.id', 'users.name')
                    ->get();

                if ($members->isEmpty()) {
                    return $this->successResponse([
                        'balances' => [],
                        'simplified_debts' => [],
                    ], 'No active members in group');
                }

                $balances = [];
                foreach ($members as $member) {
                    $balances[$member->id] = 0;
                }

                foreach ($expenses as $expense) {
                    $paidBy = $expense->paid_by;
                    $totalAmount = $expense->amount;

                    // Add amount paid by user
                    if (isset($balances[$paidBy])) {
                        $balances[$paidBy] += $totalAmount;
                    }
                    
                    foreach ($expense->shares as $share) {
                        if (!isset($balances[$share->user_id])) {
                            continue; // Skip if user is no longer in group
                        }

                        $paidAmount = $share->paid_amount;
                        
                        // Subtract what was paid back to the person who paid
                        if ($paidAmount > 0 && isset($balances[$paidBy])) {
                            $balances[$paidBy] -= $paidAmount;
                        }
                        
                        // Subtract unpaid amount from debtor
                        $unpaidAmount = $share->share_amount - $paidAmount;
                        if ($unpaidAmount > 0) {
                            $balances[$share->user_id] -= $unpaidAmount;
                        }
                    }
                }

                $balanceDetails = [];
                foreach ($balances as $userId => $balance) {
                    $user = $members->firstWhere('id', $userId);
                    if ($user) {
                        $balanceDetails[] = [
                            'user_id' => $userId,
                            'user_name' => $user->name,
                            'balance' => round($balance, 2),
                        ];
                    }
                }

                $simplifiedDebts = $this->calculateSimplifiedDebts($balanceDetails);

                return $this->successResponse([
                    'balances' => $balanceDetails,
                    'simplified_debts' => $simplifiedDebts,
                ], 'Balances calculated successfully');

            } catch (Throwable $e) {
                Log::error('Error calculating group balances', [
                    'group_id' => $group->id,
                    'user_id' => Auth::id(),
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'Failed to calculate group balances',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    null,
                    'BALANCE_CALCULATION_ERROR'
                );
            }
        });
    }

    /**
     * Calculate simplified debt structure
     */
    private function calculateSimplifiedDebts(array $balances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $balance) {
            if ($balance['balance'] > 0.01) { // Creditor (owed money)
                $creditors[] = $balance;
            } elseif ($balance['balance'] < -0.01) { // Debtor (owes money)
                $debtors[] = [
                    'user_id' => $balance['user_id'],
                    'user_name' => $balance['user_name'],
                    'balance' => abs($balance['balance']),
                ];
            }
        }

        $debts = [];
        $creditorIndex = 0;
        $debtorIndex = 0;

        while ($creditorIndex < count($creditors) && $debtorIndex < count($debtors)) {
            $creditor = $creditors[$creditorIndex];
            $debtor = $debtors[$debtorIndex];

            $amount = min($creditor['balance'], $debtor['balance']);
            
            if ($amount > 0.01) {
                $debts[] = [
                    'from_user_id' => $debtor['user_id'],
                    'from_user_name' => $debtor['user_name'],
                    'to_user_id' => $creditor['user_id'],
                    'to_user_name' => $creditor['user_name'],
                    'amount' => round($amount, 2),
                ];
            }

            $creditors[$creditorIndex]['balance'] -= $amount;
            $debtors[$debtorIndex]['balance'] -= $amount;

            if ($creditors[$creditorIndex]['balance'] <= 0.01) {
                $creditorIndex++;
            }

            if ($debtors[$debtorIndex]['balance'] <= 0.01) {
                $debtorIndex++;
            }
        }

        return $debts;
    }

    /**
     * Authorize user access to group
     */
    private function authorizeView(Group $group): void
    {
        $user = Auth::user();
        $isMember = $group->members()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'accepted')
            ->exists();

        if (!$isMember) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to access this group');
        }
    }

    /**
     * Authorize user to update group
     */
    private function authorizeUpdate(Group $group): void
    {
        $user = Auth::user();
        if ($group->created_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'Only the group creator can modify this group');
        }
    }

    /**
     * Authorize user to delete group
     */
    private function authorizeDelete(Group $group): void
    {
        $user = Auth::user();
        if ($group->created_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'Only the group creator can delete this group');
        }
    }
}
