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
        Schema::create('payment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('expense_id')->nullable();
            $table->foreignId('share_id')->nullable();
            $table->string('expense_description');
            $table->date('expense_date');
            $table->decimal('expense_total', 10, 2);
            $table->decimal('payment_amount', 10, 2);
            $table->decimal('share_amount', 10, 2);
            $table->string('payment_type'); // 'paid' or 'received'
            $table->foreignId('other_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('other_user_name');
            $table->text('notes')->nullable();
            $table->timestamp('payment_date');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('payment_date');
            $table->index('payment_type');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_history');
    }
};
