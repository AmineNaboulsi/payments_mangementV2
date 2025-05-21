<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'user_id',
        'share_amount',
        'paid_amount',
        'is_paid',
    ];

    protected $casts = [
        'share_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->share_amount - $this->paid_amount);
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->paid_amount >= $this->share_amount;
    }
}
