<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'semester' => $this->semester,
            'is_active'=> $this->is_active,
            'course'   => $this->when(
                            $this->relationLoaded('course'),
                            fn() => new CourseResource($this->course)
                          ),
            'session'  => $this->when(
                            $this->relationLoaded('academicSession'),
                            fn() => new AcademicSessionResource($this->academicSession)
                          ),
            'lecturer' => $this->when(
                            $this->relationLoaded('lecturer') && $this->lecturer,
                            fn() => [
                                'id'       => $this->lecturer->id,
                                'name'     => $this->lecturer->name,
                                'staff_id' => $this->lecturer->staff_id,
                            ]
                          ),
        ];
    }
}
