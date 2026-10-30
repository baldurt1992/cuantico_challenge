<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'status' => $this->status,
            'quality' => $this->quality,
            'issues' => $this->issues ?? [],
            'courier_id' => $this->courier_id,
            'courier_name' => $this->whenLoaded('courier', fn () => $this->courier?->name),
            'delivery_address' => $this->delivery_address,
            'planned_pickup' => $this->planned_pickup,
            'planned_destination' => $this->planned_destination,
            'created_occurred_at' => $this->created_occurred_at,
            'completed_at' => $this->completed_at,
            'cost_minor' => $this->cost_minor,
            'currency' => 'COP',
            'calculation' => $this->calculation,
        ];
    }
}
