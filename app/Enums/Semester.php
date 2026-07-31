<?php

declare(strict_types=1);

namespace App\Enums;

enum Semester: string
{
    case First = 'first';
    case Second = 'second';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::First => 'First Semester',
            self::Second => 'Second Semester',
        };
    }

    /**
     * Chronological rank within an academic session — used when replaying a
     * student's GPA history in order.
     */
    public function order(): int
    {
        return match ($this) {
            self::First => 1,
            self::Second => 2,
        };
    }
}
