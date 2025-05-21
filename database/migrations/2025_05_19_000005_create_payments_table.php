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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_share_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Add a paid_amount column to expense_shares to track total paid amount
        Schema::table('expense_shares', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0.00)->after('share_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        
        Schema::table('expense_shares', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
