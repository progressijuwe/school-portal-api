<?php

namespace App\Http\Resources;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin Grade
 */
class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'letter_grade' => $this->letter_grade,
            'grade_point' => $this->grade_point,
            'status' => $this->status,
            'ca_score' => $this->ca_score,
            'project_score' => $this->project_score,
            'exam_score' => $this->exam_score,
            'rejection_reason' => $this->when($this->rejection_reason !== null, $this->rejection_reason),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'enrollment' => $this->when(
                $this->relationLoaded('enrollment'),
                fn () => [
                    'id' => $this->enrollment->id,
                    'student' => $this->when(
                        $this->enrollment->relationLoaded('student'),
                        fn () => [
                            'id' => $this->enrollment->student->id,
                            'name' => $this->enrollment->student->name,
                            'student_id' => $this->enrollment->student->student_id,
                        ]
                    ),
                    'course' => $this->when(
                        $this->enrollment->relationLoaded('courseOffering'),
                        fn () => [
                            'title' => $this->enrollment->courseOffering->course->title,
                            'code' => $this->enrollment->courseOffering->course->code,
                            'credit_units' => $this->enrollment->courseOffering->course->credit_units,
                        ]
                    ),
                ]
            ),
            'submitted_by' => $this->when(
                $this->relationLoaded('submittedBy'),
                fn () => [
                    'id' => $this->submittedBy->id,
                    'name' => $this->submittedBy->name,
                ]
            ),
            'approved_by' => $this->when(
                $this->relationLoaded('approvedBy') && $this->approvedBy,
                fn () => [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ]
            ),
        ];
    }
}
