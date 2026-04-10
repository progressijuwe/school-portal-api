<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GpaRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'semester'                => $this->semester,
            'gpa'                     => $this->gpa,
            'cgpa'                    => $this->cgpa,
            'total_credit_units'      => $this->total_credit_units,
            'total_grade_points'      => $this->total_grade_points,
            'cumulative_credit_units' => $this->cumulative_credit_units,
            'cumulative_grade_points' => $this->cumulative_grade_points,
            'session'                 => $this->when(
                                            $this->relationLoaded('academicSession'),
                                            fn() => new AcademicSessionResource($this->academicSession)
                                          ),
        ];
    }
}