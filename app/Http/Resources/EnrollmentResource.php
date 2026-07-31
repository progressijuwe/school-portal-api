<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin Enrollment
 */
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
            'id' => $this->id,
            'status' => $this->status,
            'student' => $this->when(
                $this->relationLoaded('student'),
                fn () => [
                    'id' => $this->student->id,
                    'name' => $this->student->name,
                    'student_id' => $this->student->student_id,
                ]
            ),
            'course_offering' => $this->when(
                $this->relationLoaded('courseOffering'),
                fn () => new CourseOfferingResource($this->courseOffering)
            ),
            'enrolled_at' => $this->created_at->toDateTimeString(),
            'grade' => $this->when(
                $this->relationLoaded('grade') && $this->grade,
                fn () => new GradeResource($this->grade)
            ),
        ];
    }
}
