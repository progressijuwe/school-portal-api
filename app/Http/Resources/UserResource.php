<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'role'                  => $this->getRoleNames()->first(),
            'profile_photo_url'     => $this->profile_photo_url,
            'student_id'            => $this->when($this->student_id, $this->student_id),
            'staff_id'              => $this->when($this->staff_id, $this->staff_id),
            'department'            => $this->when(
                                        $this->relationLoaded('department') && $this->department,
                                        fn() => [
                                            'id'      => $this->department->id,
                                            'name'    => $this->department->name,
                                            'faculty' => $this->department->faculty?->name,
                                        ]
                                    ),
            'lecturer_profile'      => $this->when(
                                        $this->relationLoaded('lecturerProfile') && $this->lecturerProfile,
                                        fn() => [
                                            'prefix'                => $this->lecturerProfile->prefix,
                                            'highest_qualification' => $this->lecturerProfile->highest_qualification,
                                            'specialization'        => $this->lecturerProfile->specialization,
                                            'display_name'          => "{$this->lecturerProfile->prefix} {$this->name}",
                                        ]
                                    ),
            'study_type'            => $this->when($this->study_type, $this->study_type),
            'entry_year'            => $this->when($this->entry_year, $this->entry_year),
            'phone'                 => $this->when(
                                        $this->relationLoaded('profile') && $this->profile,
                                        fn() => $this->profile->phone
                                    ),
            'address'               => $this->when(
                                        $this->relationLoaded('profile') && $this->profile,
                                        fn() => $this->profile->address
                                    ),
            'emergency_contact'     => $this->when(
                                        $this->relationLoaded('profile') && $this->profile,
                                        fn() => [
                                            'name'  => $this->profile->emergency_contact_name,
                                            'phone' => $this->profile->emergency_contact_phone,
                                        ]
                                    ),
            'must_change_password'  => $this->must_change_password,
            'created_at'            => $this->created_at->toDateTimeString(),
            
        ];
    }
}
