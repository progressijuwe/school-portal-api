<?php

namespace App\Http\Resources;

use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin AcademicSession
 */
class AcademicSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `current_semester` is computed server-side from the session's own dates.
     * Without it every screen that needed "this semester" had to either
     * hardcode `first` — which is wrong from January onward — or re-derive it
     * in the browser from dates the sessions endpoint did not actually return.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_year' => $this->start_year,
            'end_year' => $this->end_year,
            'is_current' => $this->is_current,
            'current_semester' => $this->currentSemester(),
            'first_semester_start' => $this->first_semester_start?->toDateString(),
            'first_semester_end' => $this->first_semester_end?->toDateString(),
            'second_semester_start' => $this->second_semester_start?->toDateString(),
            'second_semester_end' => $this->second_semester_end?->toDateString(),
            // Only where the controller asked for it. The admin list uses this
            // to show what a session actually carries before anyone edits it.
            'course_offerings_count' => $this->whenCounted('courseOfferings'),
        ];
    }
}
