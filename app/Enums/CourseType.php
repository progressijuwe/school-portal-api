<?php

declare(strict_types=1);

namespace App\Enums;

enum CourseType: string
{
    case Compulsory = 'compulsory';
    case Elective = 'elective';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
