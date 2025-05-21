<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentHistory;
use Illuminate\Support\Facades\Cache;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        // Add to payment history
        $this->addToHistory($payment);
        
        // Invalidate related caches
        $this->invalidateCache($payment);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        // Update the corresponding history records
        PaymentHistory::where('payment_id', $payment->id)->delete();
        $this->addToHistory($payment);
        
        // Invalidate related caches
        $this->invalidateCache($payment);
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        // Mark history records as deleted
        PaymentHistory::where('payment_id', $payment->id)->delete();
        
        // Invalidate related caches
        $this->invalidateCache($payment);
    }

    /**
     * Add payment to history table.
     */
    private function addToHistory(Payment $payment): void
    {
        // Load related data
        $share = $payment->expenseShare;
        $expense = $share->expense;
        $group = $expense->group;
        $payer = $share->user;
        $payee = $expense->paidBy;

        // Create two history records (one for payer, one for payee)
        
        // 1. Record for the payer (user paying)
        PaymentHistory::create([
            'user_id' => $payer->id,
            'payment_id' => $payment->id,
            'group_id' => $group->id,
            'expense_id' => $expense->id,
            'share_id' => $share->id,
            'expense_description' => $expense->description,
            'expense_date' => $expense->date,
            'expense_total' => $expense->amount,
            'payment_amount' => $payment->amount,
            'share_amount' => $share->share_amount,
            'payment_type' => 'paid',
            'other_user_id' => $payee->id,
            'other_user_name' => $payee->name,
            'notes' => $payment->notes,
            'payment_date' => $payment->paid_at,
        ]);

        // 2. Record for the payee (user receiving payment)
        PaymentHistory::create([
            'user_id' => $payee->id,
            'payment_id' => $payment->id,
            'group_id' => $group->id,
            'expense_id' => $expense->id,
            'share_id' => $share->id,
            'expense_description' => $expense->description,
            'expense_date' => $expense->date,
            'expense_total' => $expense->amount,
            'payment_amount' => $payment->amount,
            'share_amount' => $share->share_amount,
            'payment_type' => 'received',
            'other_user_id' => $payer->id,
            'other_user_name' => $payer->name,
            'notes' => $payment->notes,
            'payment_date' => $payment->paid_at,
        ]);
    }
    
    /**
     * Invalidate related caches when a payment is modified.
     */
    private function invalidateCache(Payment $payment): void
    {
        try {
            $share = $payment->expenseShare;
            $expense = $share->expense;
            $group = $expense->group;
            $payer = $share->user;
            $payee = $expense->paidBy;

            // Clear specific cache keys based on what changed
            // Group balance cache
            Cache::forget("group_balances_{$group->id}_{$payer->id}");
            Cache::forget("group_balances_{$group->id}_{$payee->id}");
            
            // Share payment history cache
            Cache::forget("share_history_group_{$group->id}_expense_{$expense->id}_share_{$share->id}");
            
            // User payment history caches - we need to be more aggressive here since there are many variations
            // Clear all cache keys that start with payment_history_{user_id}
            $this->forgetCachePattern("payment_history_{$payer->id}");
            $this->forgetCachePattern("payment_history_{$payee->id}");
            
        } catch (\Exception $e) {
            // Log error but don't rethrow - we don't want to break payment processing
            // due to a cache invalidation error
            \Log::error('Error invalidating cache: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear all cache keys that start with a given pattern.
     * This is a simple implementation for the file cache driver.
     */
    private function forgetCachePattern(string $prefix): void
    {
        $cacheStore = Cache::getStore();
        
        // Only attempt this if we have a file cache store, as we know its structure
        if (get_class($cacheStore) === 'Illuminate\Cache\FileStore') {
            $cachePath = storage_path('framework/cache/data');
            $files = glob("{$cachePath}/*");
            
            foreach ($files as $file) {
                $key = basename($file);
                $cachedKey = Cache::get($key);
                
                // If we can't get the actual cache key, just try to match against the filename
                if (strpos($key, md5($prefix)) === 0 || ($cachedKey && strpos($cachedKey, $prefix) === 0)) {
                    Cache::forget($key);
                }
            }
        } else {
            // For other cache stores, we'd need a different approach
            // For now, let's just log that we can't do selective cache clearing
            \Log::info("Cannot selectively clear cache for pattern {$prefix} with the current cache driver");
        }
    }
}
