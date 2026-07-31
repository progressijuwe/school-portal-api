<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies property and method calls through to the model it
 * wraps. @mixin names that model so static analysis can see through the
 * proxy - without it, every $this->relationLoaded() and attribute access
 * looks undefined, and a genuine typo hides among the false positives.
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first(),
            'profile_photo_url' => $this->profile_photo_url,
            'student_id' => $this->when($this->student_id !== null, $this->student_id),
            'staff_id' => $this->when($this->staff_id !== null, $this->staff_id),
            'department' => $this->when(
                $this->relationLoaded('department') && $this->department,
                fn () => [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    // `faculty` stays the display name: transformProfile and the
                    // profile page render it as a string. faculty_id is added
                    // alongside because the edit form's department dropdown is
                    // gated on a faculty selection, and an id is what it needs.
                    'faculty' => $this->department->faculty->name,
                    'faculty_id' => $this->department->faculty_id,
                ]
            ),
            'lecturer_profile' => $this->when(
                $this->relationLoaded('lecturerProfile') && $this->lecturerProfile,
                fn () => [
                    'prefix' => $this->lecturerProfile->prefix,
                    'highest_qualification' => $this->lecturerProfile->highest_qualification,
                    'specialization' => $this->lecturerProfile->specialization,
                    'display_name' => "{$this->lecturerProfile->prefix} {$this->name}",
                ]
            ),
            'study_type' => $this->when($this->study_type !== null, $this->study_type),
            'entry_year' => $this->when($this->entry_year !== null, $this->entry_year),
            // Derived from the current academic session, not the calendar year.
            // Exposed here so the frontend reads one authoritative value rather
            // than recomputing the rule in getLevel.js.
            'level' => $this->when($this->entry_year !== null, fn () => $this->level),
            'phone' => $this->when(
                $this->relationLoaded('profile') && $this->profile,
                fn () => $this->profile->phone
            ),
            'address' => $this->when(
                $this->relationLoaded('profile') && $this->profile,
                fn () => $this->profile->address
            ),
            'date_of_birth' => $this->when(
                $this->relationLoaded('profile') && $this->profile,
                fn () => $this->profile->date_of_birth?->toDateString()
            ),
            'emergency_contact' => $this->when(
                $this->relationLoaded('profile') && $this->profile,
                fn () => [
                    'name' => $this->profile->emergency_contact_name,
                    'phone' => $this->profile->emergency_contact_phone,
                ]
            ),
            'must_change_password' => $this->must_change_password,
            'created_at' => $this->created_at->toDateTimeString(),

        ];
    }
}
