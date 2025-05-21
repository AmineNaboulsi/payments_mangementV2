<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentHistory extends Model
{
    use HasFactory;

    protected $table = 'payment_history';

    protected $fillable = [
        'user_id',
        'payment_id',
        'group_id',
        'expense_id',
        'share_id',
        'expense_description',
        'expense_date',
        'expense_total',
        'payment_amount',
        'share_amount',
        'payment_type',
        'other_user_id',
        'other_user_name',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'share_amount' => 'decimal:2',
        'expense_total' => 'decimal:2',
        'expense_date' => 'date',
        'payment_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function otherUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'other_user_id');
    }
}
