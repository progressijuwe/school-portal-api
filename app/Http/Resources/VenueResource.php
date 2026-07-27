<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'building'  => $this->building,
            'code'      => $this->code,
            'type'      => $this->type,
            'capacity'  => $this->capacity,
            'is_active' => $this->is_active,
        ];
    }
}
