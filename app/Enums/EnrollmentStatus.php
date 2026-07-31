<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Dropped = 'dropped';
    case Completed = 'completed';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses that occupy a seat and count toward the semester credit load.
     *
     * @return array<int, string>
     */
    public static function occupyingSeat(): array
    {
        return [self::Pending->value, self::Active->value];
    }

    /**
     * Only an active enrollment may be graded.
     */
    public function isGradable(): bool
    {
        return $this === self::Active;
    }
}
