<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentHistory;
use Illuminate\Console\Command;

class InitializePaymentHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:init-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize payment history table from existing payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing payment history table...');
        
        // Clear existing payment history
        PaymentHistory::truncate();
        $this->info('Cleared existing payment history.');
        
        // Get all payments
        $payments = Payment::with(['expenseShare.expense.group', 'expenseShare.user', 'expenseShare.expense.paidBy'])->get();
        $this->info('Found ' . $payments->count() . ' payments to process.');
        
        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();
        
        foreach ($payments as $payment) {
            $this->processPayment($payment);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Payment history initialization complete.');
    }
    
    /**
     * Process a single payment and create history records.
     */
    private function processPayment(Payment $payment)
    {
        try {
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
        } catch (\Exception $e) {
            $this->error('Error processing payment #' . $payment->id . ': ' . $e->getMessage());
        }
    }
}
