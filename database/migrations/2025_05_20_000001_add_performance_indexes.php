<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add strategic indexes to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['group_id', 'date']);
            $table->index(['paid_by', 'date']);
        });

        // Add indexes to expense_shares table
        Schema::table('expense_shares', function (Blueprint $table) {
            $table->index(['user_id', 'is_paid']);
            $table->index(['expense_id', 'user_id']);
            $table->index(['paid_amount']);
        });

        // Add indexes to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['expense_share_id', 'paid_at']);
            $table->index(['paid_at']);
        });

        // Optimize payment_history table
        Schema::table('payment_history', function (Blueprint $table) {
            $table->index(['user_id', 'payment_type', 'payment_date']);
            $table->index(['group_id', 'user_id', 'payment_date']);
            $table->index(['share_id', 'payment_date']);
            $table->index(['payment_type', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'date']);
            $table->dropIndex(['paid_by', 'date']);
        });

        // Drop indexes from expense_shares table
        Schema::table('expense_shares', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_paid']);
            $table->dropIndex(['expense_id', 'user_id']);
            $table->dropIndex(['paid_amount']);
        });

        // Drop indexes from payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['expense_share_id', 'paid_at']);
            $table->dropIndex(['paid_at']);
        });

        // Drop indexes from payment_history table
        Schema::table('payment_history', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'payment_type', 'payment_date']);
            $table->dropIndex(['group_id', 'user_id', 'payment_date']);
            $table->dropIndex(['share_id', 'payment_date']);
            $table->dropIndex(['payment_type', 'payment_date']);
        });
    }
};
