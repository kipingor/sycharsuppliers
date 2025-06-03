<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterResource extends JsonResource
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
            'meter_name' => $this->meter_name,
            'meter_number' => $this->meter_number,
            'location' => $this->location,
            'status' => $this->status,
            'type' => $this->type,
            'installation_date' => $this->installation_date,
            'resident' => new ResidentResource($this->whenLoaded('resident')),
            'billings' => BillingResource::collection($this->whenLoaded('billings')),
        ];
    }
}
