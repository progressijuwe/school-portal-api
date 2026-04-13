<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'status' => $this->status,
            'student'=> $this->when(
                            $this->relationLoaded('student'),
                            fn() => [
                                'id'         => $this->student->id,
                                'name'       => $this->student->name,
                                'student_id' => $this->student->student_id,
                            ]
                          ),
            'course_offering' => $this->when(
                                    $this->relationLoaded('courseOffering'),
                                    fn() => new CourseOfferingResource($this->courseOffering)
                                  ),
            'enrolled_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
