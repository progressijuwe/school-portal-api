<?php

namespace App\Http\Resources;

use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin TimetableSlot
 */
class TimetableSlotResource extends JsonResource
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
            'day' => $this->day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_active' => $this->is_active,
            'venue' => $this->when(
                $this->relationLoaded('venue'),
                fn () => new VenueResource($this->venue)
            ),
            'course_offering' => $this->when(
                $this->relationLoaded('courseOffering'),
                fn () => new CourseOfferingResource($this->courseOffering)
            ),
        ];
    }
}
