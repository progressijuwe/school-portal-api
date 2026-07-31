<?php

namespace App\Http\Resources;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource proxies every property and method call through to the model it
 * wraps. @mixin tells static analysis and the IDE what that model is; without
 * it, `$this->code` and `$this->relationLoaded()` look undefined.
 *
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * A course is a catalogue entry. Who teaches it and how many students are
     * on it belong to a *course offering* for a given academic session, so those
     * are only present when the controller eager-loads the relevant offering.
     * They are flattened here so the UI does not have to model that distinction.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
         * Hoisted into typed locals rather than reached through $this inside
         * each closure. A JsonResource proxies attribute access dynamically, so
         * static analysis can only see through it when the value is bound to a
         * declared type first.
         */
        /** @var CourseOffering|null $offering */
        $offering = $this->relationLoaded('offerings')
            ? $this->offerings->first()
            : null;

        /** @var Department|null $department */
        $department = $this->relationLoaded('department')
            ? $this->department
            : null;

        /** @var User|null $lecturer */
        $lecturer = $offering?->lecturer;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'code' => $this->code,
            'credit_units' => $this->credit_units,
            'level' => $this->level,
            'semester' => $this->semester,
            'type' => $this->type,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'department' => $this->when(
                $department !== null,
                fn () => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                ]
            ),
            'lecturer' => $this->when(
                $this->relationLoaded('offerings'),
                function () use ($lecturer) {
                    if ($lecturer === null) {
                        return null;
                    }

                    $prefix = $lecturer->lecturerProfile?->prefix;

                    return [
                        'id' => $lecturer->id,
                        'name' => $prefix !== null
                            ? "{$prefix} {$lecturer->name}"
                            : $lecturer->name,
                    ];
                }
            ),
            'enrolled' => $this->when(
                $this->relationLoaded('offerings'),
                fn () => $offering !== null ? (int) $offering->enrollments_count : 0
            ),
            'offering_id' => $this->when(
                $this->relationLoaded('offerings'),
                fn () => $offering?->id
            ),
        ];
    }
}
