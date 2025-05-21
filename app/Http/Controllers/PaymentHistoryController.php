<?php

namespace App\Http\Controllers;

use App\Helpers\RedisCacheHelper;
use App\Http\Resources\PaymentHistoryResource;
use App\Models\ExpenseShare;
use App\Models\Group;
use App\Models\PaymentHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $cacheKey = "payment_history_{$user->id}";
        
        if ($request->has('group_id')) {
            $cacheKey .= "_group_{$request->group_id}";
        }
        
        if ($request->has('type') && in_array($request->type, ['paid', 'received'])) {
            $cacheKey .= "_type_{$request->type}";
        }
        
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $cacheKey .= "_page_{$page}_per_{$perPage}";
        
        return RedisCacheHelper::remember($cacheKey, 15, function() use ($user, $request, $perPage) {
            $query = PaymentHistory::where('user_id', $user->id);
            
            if ($request->has('group_id')) {
                $query->where('group_id', $request->group_id);
            }
            
            if ($request->has('type') && in_array($request->type, ['paid', 'received'])) {
                $query->where('payment_type', $request->type);
            }
            
            if ($request->has('date_from')) {
                $query->where('payment_date', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->where('payment_date', '<=', $request->date_to);
            }
            
            $query->orderBy('payment_date', 'desc');
            
            $paymentHistory = $query->paginate($perPage);
            
            return PaymentHistoryResource::collection($paymentHistory);
        });
    }
    
    public function shareHistory(Group $group, $expenseId, $shareId)
    {
        $this->authorizeView($group);
        
        $cacheKey = "share_history_group_{$group->id}_expense_{$expenseId}_share_{$shareId}";
        
        return RedisCacheHelper::remember($cacheKey, 30, function() use ($group, $expenseId, $shareId) {
            $share = ExpenseShare::with(['expense.paidBy', 'user'])
                ->where('id', $shareId)
                ->firstOrFail();
            
            if ($share->expense_id != $expenseId) {
                abort(Response::HTTP_NOT_FOUND, 'Share does not belong to the specified expense');
            }
            
            if ($share->expense->group_id != $group->id) {
                abort(Response::HTTP_NOT_FOUND, 'Expense does not belong to the specified group');
            }
            
            $paymentRecords = PaymentHistory::where('share_id', $shareId)
                ->orderBy('payment_date', 'desc')
                ->get();
            
            $paymentHistory = $paymentRecords->map(function ($record) {
                return [
                    'id' => $record->id,
                    'date' => $record->payment_date,
                    'amount' => $record->payment_amount,
                    'notes' => $record->notes,
                    'payer' => [
                        'id' => $record->payment_type === 'paid' ? $record->user_id : $record->other_user_id,
                        'name' => $record->payment_type === 'paid' ? $record->user->name : $record->other_user_name
                    ],
                    'receiver' => [
                        'id' => $record->payment_type === 'received' ? $record->user_id : $record->other_user_id,
                        'name' => $record->payment_type === 'received' ? $record->user->name : $record->other_user_name
                    ]
                ];
            });
            
            $shareDetails = [
                'id' => $share->id,
                'user' => [
                    'id' => $share->user_id,
                    'name' => $share->user->name
                ],
                'total_amount' => $share->share_amount,
                'paid_amount' => $share->paid_amount,
                'remaining_amount' => $share->share_amount - $share->paid_amount,
                'is_fully_paid' => $share->is_paid,
                'expense' => [
                    'id' => $share->expense->id,
                    'description' => $share->expense->description,
                    'date' => $share->expense->date,
                    'total_amount' => $share->expense->amount,
                    'paid_by' => [
                        'id' => $share->expense->paid_by,
                        'name' => $share->expense->paidBy->name
                    ]
                ]
            ];
            
            return [
                'share' => $shareDetails,
                'payments' => $paymentHistory
            ];
        });
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
}
