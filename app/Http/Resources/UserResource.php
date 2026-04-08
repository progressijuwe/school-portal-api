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
                'department'        => $this->when(
                                        $this->relationLoaded('department') && $this->department,
                                        fn() => [
                                            'id'      => $this->department->id,
                                            'name'    => $this->department->name,
                                            'faculty' => $this->department->faculty?->name,
                                        ]
                                    ),
            'study_type'            => $this->when($this->study_type, $this->study_type),
            'entry_year'            => $this->when($this->entry_year, $this->entry_year),
            'must_change_password'  => $this->must_change_password,
            'created_at'            => $this->created_at->toDateTimeString(),
        ];
    }
}
