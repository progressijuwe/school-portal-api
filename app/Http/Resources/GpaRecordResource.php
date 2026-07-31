<?php

namespace App\Http\Resources;

use App\Models\GpaRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin GpaRecord
 */
class GpaRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'semester' => $this->semester,
            'gpa' => $this->gpa,
            'cgpa' => $this->cgpa,
            'total_credit_units' => $this->total_credit_units,
            'total_grade_points' => $this->total_grade_points,
            'cumulative_credit_units' => $this->cumulative_credit_units,
            'cumulative_grade_points' => $this->cumulative_grade_points,
            'session' => $this->when(
                $this->relationLoaded('academicSession'),
                fn () => new AcademicSessionResource($this->academicSession)
            ),
        ];
    }
}
