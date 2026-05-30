<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_type' => $this->booking_type,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_price' => $this->total_price,
            'discount_amount' => $this->discount_amount,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'room' => $this->whenLoaded('room'),
            'sitter' => $this->whenLoaded('sitter'),
            'cats' => $this->whenLoaded('cats'),
            'sitter_review' => $this->whenLoaded('sitterReview'),
            'created_at' => $this->created_at,
            // Internal/Admin fields
            $this->mergeWhen($request->user() && $request->user()->role === 'admin', [
                'midtrans_order_id' => $this->midtrans_order_id,
                'refund_status' => $this->refund_status,
                'refund_amount' => $this->refund_amount,
            ]),
        ];
    }
}
