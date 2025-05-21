<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date,
            'group_id' => $this->group_id,
            'paid_by' => [
                'id' => $this->paidBy->id ?? null,
                'name' => $this->paidBy->name ?? null,
            ],
            'shares' => $this->when($this->shares, function() {
                return $this->shares->map(function($share) {
                    return [
                        'id' => $share->id,
                        'user_id' => $share->user_id,
                        'user_name' => $share->user->name ?? null,
                        'share_amount' => $share->share_amount,
                        'paid_amount' => $share->paid_amount,
                        'is_paid' => $share->is_paid,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
