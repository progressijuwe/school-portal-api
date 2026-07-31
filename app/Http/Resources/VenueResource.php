<?php

namespace App\Http\Resources;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin Venue
 */
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
            'id' => $this->id,
            'name' => $this->name,
            'building' => $this->building,
            'code' => $this->code,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
        ];
    }
}
