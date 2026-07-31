<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\TimetableSlot;

class TimetableService
{
    /**
     * Check all conflicts for a proposed timetable slot.
     * Returns an array of conflict messages, empty if none.
     */
    public function checkConflicts(
        int $courseOfferingId,
        int $venueId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $excludeSlotId = null
    ): array {
        $conflicts = [];

        $offering = CourseOffering::with('course', 'lecturer')->find($courseOfferingId);

        // 1. Venue conflict — same venue, same day, overlapping time
        $venueConflict = $this->hasTimeOverlap(
            $venueId,
            $day,
            $startTime,
            $endTime,
            $excludeSlotId,
            'venue_id'
        );

        if ($venueConflict) {
            $conflicts[] = 'This venue is already booked during this time slot.';
        }

        // 2. Lecturer conflict — same lecturer, same day, overlapping time
        if ($offering->lecturer_id) {
            $lecturerSlots = TimetableSlot::where('day', $day)
                ->where('is_active', true)
                ->when($excludeSlotId, fn ($q) => $q->where('id', '!=', $excludeSlotId))
                ->whereHas('courseOffering', fn ($q) => $q->where('lecturer_id', $offering->lecturer_id)
                )
                ->get();

            foreach ($lecturerSlots as $slot) {
                if ($this->timesOverlap($startTime, $endTime, $slot->start_time, $slot->end_time)) {
                    $conflicts[] = 'The assigned lecturer already has a class during this time.';
                    break;
                }
            }
        }

        // 3. Department/level conflict — same department, same level, same day, overlapping time
        $deptLevelSlots = TimetableSlot::where('day', $day)
            ->where('is_active', true)
            ->when($excludeSlotId, fn ($q) => $q->where('id', '!=', $excludeSlotId))
            ->whereHas('courseOffering.course', function ($q) use ($offering) {
                $q->where('department_id', $offering->course->department_id)
                    ->where('level', $offering->course->level);
            })
            ->get();

        foreach ($deptLevelSlots as $slot) {
            if ($this->timesOverlap($startTime, $endTime, $slot->start_time, $slot->end_time)) {
                $conflicts[] = 'Students at this level in this department already have a class during this time.';
                break;
            }
        }

        return $conflicts;
    }

    protected function hasTimeOverlap(
        int $id,
        string $day,
        string $startTime,
        string $endTime,
        ?int $excludeSlotId,
        string $column
    ): bool {
        $slots = TimetableSlot::where($column, $id)
            ->where('day', $day)
            ->where('is_active', true)
            ->when($excludeSlotId, fn ($q) => $q->where('id', '!=', $excludeSlotId))
            ->get();

        foreach ($slots as $slot) {
            if ($this->timesOverlap($startTime, $endTime, $slot->start_time, $slot->end_time)) {
                return true;
            }
        }

        return false;
    }

    protected function timesOverlap(
        string $startA,
        string $endA,
        string $startB,
        string $endB
    ): bool {
        return $startA < $endB && $endA > $startB;
    }
}
