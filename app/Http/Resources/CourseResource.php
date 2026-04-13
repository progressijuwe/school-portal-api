<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'code'         => $this->code,
            'credit_units' => $this->credit_units,
            'level'        => $this->level,
            'semester'     => $this->semester,
            'type'         => $this->type,
            'description'  => $this->description,
            'is_active'    => $this->is_active,
            'department'   => $this->when(
                                $this->relationLoaded('department'),
                                fn() => [
                                    'id'   => $this->department->id,
                                    'name' => $this->department->name,
                                    'code' => $this->department->code,
                                ]
                              ),
        ];
    }
}
