<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * An approved grade is final and counts toward the student's GPA.
     */
    public function isFinal(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Drafts are the lecturer's private working copy — no letter grade is
     * resolved and admins never see them in the approval queue.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }
}
