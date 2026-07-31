<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeAuditAction: string
{
    case DraftSaved = 'draft_saved';
    case Submitted = 'submitted';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
