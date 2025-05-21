<?php

namespace App\Http\Controllers;

use App\Helpers\RedisCacheHelper;
use App\Models\Expense;
use App\Models\ExpenseShare;
use App\Models\Group;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Group $group)
    {
        $this->authorizeView($group);
        $user = Auth::user();
        
        $cacheKey = "group_expenses_{$group->id}_{$user->id}";
        $expenses = RedisCacheHelper::remember($cacheKey, 5, function() use ($group) {
            return $group->expenses()
                ->with('paidBy', 'shares.user')
                ->orderBy('date', 'desc')
                ->get();
        });

        return response()->json([
            'expenses' => $expenses,
        ]);
    }

    public function store(Request $request, Group $group)
    {
        $this->authorizeView($group);
        $user = Auth::user();

        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'paid_by' => ['required', 'integer', 'exists:users,id'],
            'shared_with' => ['required', 'array', 'min:1'],
            'shared_with.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $members = $group->members()->where('status', 'accepted')->pluck('user_id')->toArray();
        
        if (!in_array($request->paid_by, $members)) {
            return response()->json([
                'message' => 'The payer must be a member of the group',
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($request->shared_with as $userId) {
            if (!in_array($userId, $members)) {
                return response()->json([
                    'message' => 'All participants must be members of the group',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'group_id' => $group->id,
                'paid_by' => $request->paid_by,
                'description' => $request->description,
                'amount' => $request->amount,
                'date' => $request->date,
            ]);

            $shareAmount = $request->amount / count($request->shared_with);
            $shareAmount = round($shareAmount, 2);

            foreach ($request->shared_with as $userId) {
                ExpenseShare::create([
                    'expense_id' => $expense->id,
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                    'is_paid' => $userId == $request->paid_by,
                ]);
            }

            DB::commit();
            
            RedisCacheHelper::forget("group_expenses_{$group->id}_{$user->id}");
            $memberIds = $group->members()->pluck('user_id')->toArray();
            foreach ($memberIds as $memberId) {
                RedisCacheHelper::forget("user_groups:{$memberId}");
                RedisCacheHelper::forget("group_balances_{$group->id}_{$memberId}");
            }

            $expense->load('paidBy', 'shares.user');

            return response()->json([
                'message' => 'Expense created successfully',
                'expense' => $expense,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(Group $group, Expense $expense)
    {
        $this->authorizeView($group);
        $this->validateExpenseBelongsToGroup($group, $expense);
        $user = Auth::user();
        
        $cacheKey = "expense_details_{$expense->id}_{$user->id}";
        $expenseData = RedisCacheHelper::remember($cacheKey, 5, function() use ($expense) {
            $expense->load('paidBy', 'shares.user');
            return $expense;
        });

        return response()->json([
            'expense' => $expenseData,
        ]);
    }

    public function update(Request $request, Group $group, Expense $expense)
    {
        $this->authorizeView($group);
        $this->validateExpenseBelongsToGroup($group, $expense);
        $this->authorizeUpdate($expense);

        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'paid_by' => ['required', 'integer', 'exists:users,id'],
            'shared_with' => ['required', 'array', 'min:1'],
            'shared_with.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $members = $group->members()->where('status', 'accepted')->pluck('user_id')->toArray();
        
        if (!in_array($request->paid_by, $members)) {
            return response()->json([
                'message' => 'The payer must be a member of the group',
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($request->shared_with as $userId) {
            if (!in_array($userId, $members)) {
                return response()->json([
                    'message' => 'All participants must be members of the group',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        DB::beginTransaction();
        try {
            $expense->update([
                'paid_by' => $request->paid_by,
                'description' => $request->description,
                'amount' => $request->amount,
                'date' => $request->date,
            ]);

            $expense->shares()->delete();

            $shareAmount = $request->amount / count($request->shared_with);
            $shareAmount = round($shareAmount, 2);

            foreach ($request->shared_with as $userId) {
                ExpenseShare::create([
                    'expense_id' => $expense->id,
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                    'is_paid' => $userId == $request->paid_by,
                ]);
            }

            DB::commit();
            
            RedisCacheHelper::forget("group_expenses_{$group->id}_{$user->id}");
            RedisCacheHelper::forget("expense_details_{$expense->id}_{$user->id}");
            
            $memberIds = $group->members()->pluck('user_id')->toArray();
            foreach ($memberIds as $memberId) {
                RedisCacheHelper::forget("group_balances_{$group->id}_{$memberId}");
            }

            $expense->load('paidBy', 'shares.user');

            return response()->json([
                'message' => 'Expense updated successfully',
                'expense' => $expense,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(Group $group, Expense $expense)
    {
        $this->authorizeView($group);
        $this->validateExpenseBelongsToGroup($group, $expense);
        $this->authorizeUpdate($expense);
        
        $expenseId = $expense->id;
        $expense->delete();
        
        RedisCacheHelper::forget("group_expenses_{$group->id}_{$user->id}");
        RedisCacheHelper::forget("expense_details_{$expenseId}_{$user->id}");
        
        $memberIds = $group->members()->pluck('user_id')->toArray();
        foreach ($memberIds as $memberId) {
            RedisCacheHelper::forget("group_balances_{$group->id}_{$memberId}");
        }

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    public function markShareAsPaid(Group $group, Expense $expense, ExpenseShare $share)
    {
        $this->authorizeView($group);
        $this->validateExpenseBelongsToGroup($group, $expense);
        $this->validateShareBelongsToExpense($expense, $share);

        if ($share->is_paid) {
            return response()->json([
                'message' => 'This share is already fully paid',
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $remainingAmount = $share->share_amount - $share->paid_amount;

            $payment = Payment::create([
                'expense_share_id' => $share->id,
                'amount' => $remainingAmount,
                'paid_at' => now(),
                'notes' => 'Full payment',
            ]);

            $share->update([
                'paid_amount' => $share->share_amount,
                'is_paid' => true,
            ]);

            DB::commit();
            
            RedisCacheHelper::forget("group_expenses_{$group->id}_{$user->id}");
            RedisCacheHelper::forget("expense_details_{$expense->id}_{$user->id}");
            
            $memberIds = $group->members()->pluck('user_id')->toArray();
            foreach ($memberIds as $memberId) {
                RedisCacheHelper::forget("group_balances_{$group->id}_{$memberId}");
            }

            return response()->json([
                'message' => 'Share marked as paid successfully',
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function recordPartialPayment(Request $request, Group $group, Expense $expense, ExpenseShare $share)
    {
        $this->authorizeView($group);
        $this->validateExpenseBelongsToGroup($group, $expense);
        $this->validateShareBelongsToExpense($expense, $share);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($share->is_paid) {
            return response()->json([
                'message' => 'This share is already fully paid',
            ], Response::HTTP_BAD_REQUEST);
        }

        $remainingAmount = $share->share_amount - $share->paid_amount;
        
        if ($request->amount > $remainingAmount) {
            return response()->json([
                'message' => 'Payment amount exceeds the remaining amount',
                'remaining_amount' => $remainingAmount,
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'expense_share_id' => $share->id,
                'amount' => $request->amount,
                'paid_at' => now(),
                'notes' => $request->notes,
            ]);

            $newPaidAmount = $share->paid_amount + $request->amount;
            $isFullyPaid = $newPaidAmount >= $share->share_amount;
            
            $share->update([
                'paid_amount' => $newPaidAmount,
                'is_paid' => $isFullyPaid,
            ]);

            DB::commit();
            
            RedisCacheHelper::forget("group_expenses_{$group->id}_{$user->id}");
            RedisCacheHelper::forget("expense_details_{$expense->id}_{$user->id}");
            
            $memberIds = $group->members()->pluck('user_id')->toArray();
            foreach ($memberIds as $memberId) {
                RedisCacheHelper::forget("group_balances_{$group->id}_{$memberId}");
            }

            return response()->json([
                'message' => $isFullyPaid ? 'Share fully paid' : 'Partial payment recorded successfully',
                'payment' => $payment,
                'remaining_amount' => $share->share_amount - $newPaidAmount,
                'is_fully_paid' => $isFullyPaid,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function authorizeView(Group $group)
    {
        $user = Auth::user();
        $isMember = $group->members()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if (!$isMember) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view this group');
        }
    }

    private function authorizeUpdate(Expense $expense)
    {
        $user = Auth::user();
        if ($expense->paid_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to update this expense');
        }
    }

    private function validateExpenseBelongsToGroup(Group $group, Expense $expense)
    {
        if ($expense->group_id !== $group->id) {
            abort(Response::HTTP_NOT_FOUND, 'Expense not found in this group'); 
        }
    }

    private function validateShareBelongsToExpense(Expense $expense, ExpenseShare $share)
    {
        if ($share->expense_id !== $expense->id) {
            abort(Response::HTTP_NOT_FOUND, 'Share not found for this expense');
        }
    }
}
