<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id'         => $this->id,
            'day'        => $this->day,
            'start_time' => $this->start_time,
            'end_time'   => $this->end_time,
            'is_active'  => $this->is_active,
            'venue'      => $this->when(
                                $this->relationLoaded('venue'),
                                fn() => new VenueResource($this->venue)
                            ),
            'course_offering' => $this->when(
                                    $this->relationLoaded('courseOffering'),
                                    fn() => new CourseOfferingResource($this->courseOffering)
                                  ),
        ];
    }
}
