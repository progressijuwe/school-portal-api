<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'score'            => $this->score,
            'letter_grade'     => $this->letter_grade,
            'grade_point'      => $this->grade_point,
            'status'           => $this->status,
            'rejection_reason' => $this->when($this->rejection_reason, $this->rejection_reason),
            'submitted_at'     => $this->submitted_at?->toDateTimeString(),
            'approved_at'      => $this->approved_at?->toDateTimeString(),
            'enrollment'       => $this->when(
                                    $this->relationLoaded('enrollment'),
                                    fn() => [
                                        'id'      => $this->enrollment->id,
                                        'student' => $this->when(
                                            $this->enrollment->relationLoaded('student'),
                                            fn() => [
                                                'id'         => $this->enrollment->student->id,
                                                'name'       => $this->enrollment->student->name,
                                                'student_id' => $this->enrollment->student->student_id,
                                            ]
                                        ),
                                        'course'  => $this->when(
                                            $this->enrollment->relationLoaded('courseOffering'),
                                            fn() => [
                                                'title'        => $this->enrollment->courseOffering->course->title,
                                                'code'         => $this->enrollment->courseOffering->course->code,
                                                'credit_units' => $this->enrollment->courseOffering->course->credit_units,
                                            ]
                                        ),
                                    ]
                                  ),
            'submitted_by'     => $this->when(
                                    $this->relationLoaded('submittedBy'),
                                    fn() => [
                                        'id'   => $this->submittedBy->id,
                                        'name' => $this->submittedBy->name,
                                    ]
                                  ),
            'approved_by'      => $this->when(
                                    $this->relationLoaded('approvedBy') && $this->approvedBy,
                                    fn() => [
                                        'id'   => $this->approvedBy->id,
                                        'name' => $this->approvedBy->name,
                                    ]
                                  ),
        ];
    }
}