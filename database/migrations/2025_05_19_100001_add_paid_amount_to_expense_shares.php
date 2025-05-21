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
        // Skip adding paid_amount column if it already exists
        if (!Schema::hasColumn('expense_shares', 'paid_amount')) {
            Schema::table('expense_shares', function (Blueprint $table) {
                $table->decimal('paid_amount', 10, 2)->default(0.00)->after('share_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('expense_shares', 'paid_amount')) {
            Schema::table('expense_shares', function (Blueprint $table) {
                $table->dropColumn('paid_amount');
            });
        }
    }
};
