<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseShare;
use App\Models\Group;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpenseController extends Controller
{
    public function index(Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($group) {
            $this->authorizeView($group);
            
            $expenses = $group->expenses()
                ->with('paidBy', 'shares.user')
                ->orderBy('date', 'desc')
                ->get();

            return $this->successResponse([
                'expenses' => $expenses,
            ], 'Expenses retrieved successfully');
        });
    }

    public function store(Request $request, Group $group): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group) {
            $this->authorizeView($group);

            $validatedData = $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
                'date' => ['required', 'date', 'before_or_equal:today'],
                'paid_by' => ['required', 'integer', 'exists:users,id'],
                'shared_with' => ['required', 'array', 'min:1', 'max:50'],
                'shared_with.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            ]);

            // Verify group membership
            $members = $group->members()
                ->wherePivot('status', 'accepted')
                ->pluck('user_id')
                ->toArray();
            
            if (!in_array($validatedData['paid_by'], $members)) {
                return $this->badRequestResponse('The payer must be an accepted member of the group');
            }

            foreach ($validatedData['shared_with'] as $userId) {
                if (!in_array($userId, $members)) {
                    return $this->badRequestResponse('All participants must be accepted members of the group');
                }
            }

            DB::beginTransaction();
            try {
                $expense = Expense::create([
                    'group_id' => $group->id,
                    'paid_by' => $validatedData['paid_by'],
                    'description' => $validatedData['description'],
                    'amount' => $validatedData['amount'],
                    'date' => $validatedData['date'],
                ]);

                $shareAmount = round($validatedData['amount'] / count($validatedData['shared_with']), 2);

                foreach ($validatedData['shared_with'] as $userId) {
                    ExpenseShare::create([
                        'expense_id' => $expense->id,
                        'user_id' => $userId,
                        'share_amount' => $shareAmount,
                        'is_paid' => $userId == $validatedData['paid_by'],
                        'paid_amount' => $userId == $validatedData['paid_by'] ? $shareAmount : 0,
                    ]);
                }

                DB::commit();

                $expense->load('paidBy', 'shares.user');

                Log::info('Expense created successfully', [
                    'expense_id' => $expense->id,
                    'group_id' => $group->id,
                    'amount' => $expense->amount,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'expense' => $expense,
                ], 'Expense created successfully', Response::HTTP_CREATED);

            } catch (Throwable $e) {
                DB::rollBack();
                return $this->handleDatabaseError($e, 'expense creation');
            }
        });
    }

    public function show(Group $group, Expense $expense): JsonResponse
    {
        return $this->handleRequest(function () use ($group, $expense) {
            $this->authorizeView($group);
            $this->validateExpenseBelongsToGroup($group, $expense);
            
            $expense->load('paidBy', 'shares.user');

            return $this->successResponse([
                'expense' => $expense,
            ], 'Expense retrieved successfully');
        });
    }

    public function update(Request $request, Group $group, Expense $expense): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group, $expense) {
            $this->authorizeView($group);
            $this->validateExpenseBelongsToGroup($group, $expense);
            $this->authorizeUpdate($expense);

            $validatedData = $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
                'date' => ['required', 'date', 'before_or_equal:today'],
                'paid_by' => ['required', 'integer', 'exists:users,id'],
                'shared_with' => ['required', 'array', 'min:1', 'max:50'],
                'shared_with.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            ]);

            // Verify group membership
            $members = $group->members()
                ->wherePivot('status', 'accepted')
                ->pluck('user_id')
                ->toArray();
            
            if (!in_array($validatedData['paid_by'], $members)) {
                return $this->badRequestResponse('The payer must be an accepted member of the group');
            }

            foreach ($validatedData['shared_with'] as $userId) {
                if (!in_array($userId, $members)) {
                    return $this->badRequestResponse('All participants must be accepted members of the group');
                }
            }

            // Check if expense has been partially paid
            $hasPaidShares = $expense->shares()->where('paid_amount', '>', 0)->exists();
            if ($hasPaidShares && ($expense->amount != $validatedData['amount'] || 
                                  count($validatedData['shared_with']) != $expense->shares()->count())) {
                return $this->badRequestResponse('Cannot modify amount or participants for expenses with existing payments');
            }

            DB::beginTransaction();
            try {
                $expense->update([
                    'paid_by' => $validatedData['paid_by'],
                    'description' => $validatedData['description'],
                    'amount' => $validatedData['amount'],
                    'date' => $validatedData['date'],
                ]);

                // Only recreate shares if participants changed or no payments exist
                if (!$hasPaidShares) {
                    $expense->shares()->delete();

                    $shareAmount = round($validatedData['amount'] / count($validatedData['shared_with']), 2);

                    foreach ($validatedData['shared_with'] as $userId) {
                        ExpenseShare::create([
                            'expense_id' => $expense->id,
                            'user_id' => $userId,
                            'share_amount' => $shareAmount,
                            'is_paid' => $userId == $validatedData['paid_by'],
                            'paid_amount' => $userId == $validatedData['paid_by'] ? $shareAmount : 0,
                        ]);
                    }
                }

                DB::commit();

                $expense->load('paidBy', 'shares.user');

                Log::info('Expense updated successfully', [
                    'expense_id' => $expense->id,
                    'group_id' => $group->id,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'expense' => $expense,
                ], 'Expense updated successfully');

            } catch (Throwable $e) {
                DB::rollBack();
                return $this->handleDatabaseError($e, 'expense update');
            }
        });
    }

    public function destroy(Group $group, Expense $expense): JsonResponse
    {
        return $this->handleRequest(function () use ($group, $expense) {
            $this->authorizeView($group);
            $this->validateExpenseBelongsToGroup($group, $expense);
            $this->authorizeUpdate($expense);

            // Check if expense has any payments
            $hasPaidShares = $expense->shares()->where('paid_amount', '>', 0)->exists();
            if ($hasPaidShares) {
                return $this->badRequestResponse('Cannot delete expense that has existing payments');
            }

            try {
                $expenseId = $expense->id;
                $expense->delete();

                Log::info('Expense deleted successfully', [
                    'expense_id' => $expenseId,
                    'group_id' => $group->id,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse(null, 'Expense deleted successfully');

            } catch (Throwable $e) {
                return $this->handleDatabaseError($e, 'expense deletion');
            }
        });
    }

    public function markShareAsPaid(Group $group, Expense $expense, ExpenseShare $share): JsonResponse
    {
        return $this->handleRequest(function () use ($group, $expense, $share) {
            $this->authorizeView($group);
            $this->validateExpenseBelongsToGroup($group, $expense);
            $this->validateShareBelongsToExpense($expense, $share);

            if ($share->is_paid) {
                return $this->badRequestResponse('This share is already fully paid');
            }

            $user = Auth::user();
            if ($share->user_id !== $user->id && $expense->paid_by !== $user->id) {
                return $this->forbiddenResponse('You can only mark your own shares as paid or manage expenses you created');
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

                Log::info('Share marked as paid', [
                    'share_id' => $share->id,
                    'expense_id' => $expense->id,
                    'amount' => $remainingAmount,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'payment' => $payment,
                    'share' => $share->fresh(),
                ], 'Share marked as paid successfully');

            } catch (Throwable $e) {
                DB::rollBack();
                return $this->handleDatabaseError($e, 'payment recording');
            }
        });
    }

    public function recordPartialPayment(Request $request, Group $group, Expense $expense, ExpenseShare $share): JsonResponse
    {
        return $this->handleRequest(function () use ($request, $group, $expense, $share) {
            $this->authorizeView($group);
            $this->validateExpenseBelongsToGroup($group, $expense);
            $this->validateShareBelongsToExpense($expense, $share);

            $validatedData = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'notes' => ['nullable', 'string', 'max:255'],
            ]);

            if ($share->is_paid) {
                return $this->badRequestResponse('This share is already fully paid');
            }

            $user = Auth::user();
            if ($share->user_id !== $user->id && $expense->paid_by !== $user->id) {
                return $this->forbiddenResponse('You can only record payments for your own shares or manage expenses you created');
            }

            $remainingAmount = $share->share_amount - $share->paid_amount;
            
            if ($validatedData['amount'] > $remainingAmount) {
                return $this->badRequestResponse(
                    'Payment amount exceeds the remaining amount',
                    ['remaining_amount' => $remainingAmount]
                );
            }

            DB::beginTransaction();
            try {
                $payment = Payment::create([
                    'expense_share_id' => $share->id,
                    'amount' => $validatedData['amount'],
                    'paid_at' => now(),
                    'notes' => $validatedData['notes'],
                ]);

                $newPaidAmount = $share->paid_amount + $validatedData['amount'];
                $isFullyPaid = $newPaidAmount >= $share->share_amount;
                
                $share->update([
                    'paid_amount' => $newPaidAmount,
                    'is_paid' => $isFullyPaid,
                ]);

                DB::commit();

                Log::info('Partial payment recorded', [
                    'share_id' => $share->id,
                    'expense_id' => $expense->id,
                    'amount' => $validatedData['amount'],
                    'is_fully_paid' => $isFullyPaid,
                    'user_id' => Auth::id(),
                ]);

                return $this->successResponse([
                    'payment' => $payment,
                    'share' => $share->fresh(),
                    'remaining_amount' => $share->share_amount - $newPaidAmount,
                    'is_fully_paid' => $isFullyPaid,
                ], $isFullyPaid ? 'Share fully paid' : 'Partial payment recorded successfully');

            } catch (Throwable $e) {
                DB::rollBack();
                return $this->handleDatabaseError($e, 'payment recording');
            }
        });
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
     * Authorize user to update expense
     */
    private function authorizeUpdate(Expense $expense): void
    {
        $user = Auth::user();
        if ($expense->paid_by !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You can only modify expenses you created');
        }
    }

    /**
     * Validate expense belongs to group
     */
    private function validateExpenseBelongsToGroup(Group $group, Expense $expense): void
    {
        if ($expense->group_id !== $group->id) {
            abort(Response::HTTP_NOT_FOUND, 'Expense not found in this group'); 
        }
    }

    /**
     * Validate share belongs to expense
     */
    private function validateShareBelongsToExpense(Expense $expense, ExpenseShare $share): void
    {
        if ($share->expense_id !== $expense->id) {
            abort(Response::HTTP_NOT_FOUND, 'Share not found for this expense');
        }
    }
}
