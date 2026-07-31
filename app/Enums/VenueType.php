<?php

declare(strict_types=1);

namespace App\Enums;

enum VenueType: string
{
    case LectureHall = 'lecture_hall';
    case Laboratory = 'laboratory';
    case SeminarRoom = 'seminar_room';
    case Workshop = 'workshop';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
