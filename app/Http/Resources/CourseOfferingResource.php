<?php

namespace App\Http\Resources;

use App\Models\CourseOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin CourseOffering
 */
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
            'id' => $this->id,
            'semester' => $this->semester,
            'is_active' => $this->is_active,
            'course' => $this->when(
                $this->relationLoaded('course'),
                fn () => new CourseResource($this->course)
            ),
            'session' => $this->when(
                $this->relationLoaded('academicSession'),
                fn () => new AcademicSessionResource($this->academicSession)
            ),
            'lecturer' => $this->when(
                $this->relationLoaded('lecturer') && $this->lecturer,
                fn () => [
                    'id' => $this->lecturer->id,
                    'name' => $this->lecturer->name,
                    // The prefix lives on the lecturer profile, so a list that
                    // showed the bare name read "Test Lecturer One" where every
                    // other screen in the portal says "Dr. Test Lecturer One".
                    'display_name' => $this->lecturer->lecturerProfile?->prefix
                        ? "{$this->lecturer->lecturerProfile->prefix} {$this->lecturer->name}"
                        : $this->lecturer->name,
                    'staff_id' => $this->lecturer->staff_id,
                ]
            ),
            // Present only where the controller asked for the count, so a
            // caller that did not pay for the subquery does not get a
            // misleading zero.
            'enrolled_count' => $this->whenCounted('enrollments'),
        ];
    }
}
