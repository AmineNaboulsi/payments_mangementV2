<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentHistoryResource extends JsonResource
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
            'date' => $this->payment_date,
            'amount' => $this->payment_amount,
            'notes' => $this->notes,
            'expense' => [
                'id' => $this->expense_id,
                'description' => $this->expense_description,
                'date' => $this->expense_date,
                'total_amount' => $this->expense_total
            ],
            'group' => [
                'id' => $this->group_id,
                'name' => $this->when($this->group, $this->group->name)
            ],
            'share' => [
                'id' => $this->share_id,
                'amount' => $this->share_amount
            ],
            'type' => $this->payment_type,
            'direction' => $this->payment_type === 'paid' ? 'to' : 'from',
            'other_user' => [
                'id' => $this->other_user_id,
                'name' => $this->other_user_name
            ]
        ];
    }
}
