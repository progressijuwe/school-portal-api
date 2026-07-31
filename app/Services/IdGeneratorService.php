<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use RuntimeException;

class IdGeneratorService
{
    /**
     * How many times to retry when a generated identifier collides.
     *
     * The check-then-insert pattern is inherently racy: two concurrent creates
     * can both find an identifier free and both try to claim it. The unique
     * index is the real guard — callers should catch the constraint violation
     * and retry — but retrying here keeps the window small enough that they
     * almost never have to.
     */
    private const MAX_ATTEMPTS = 20;

    /** @var array<string, string> */
    private const STUDY_TYPE_CODES = [
        'Undergraduate' => 'U',
        'Postgraduate' => 'PG',
    ];

    public function generateStudentId(int $departmentId, string $studyType, int $entryYear): string
    {
        $department = Department::findOrFail($departmentId);
        $studyTypeCode = self::STUDY_TYPE_CODES[$studyType]
            ?? throw new RuntimeException("Unknown study type: {$studyType}");

        $yearCode = substr((string) $entryYear, 2).$studyTypeCode;

        return $this->generateUnique(
            'student_id',
            fn () => sprintf(
                '%s/%s/%06d',
                $department->code,
                $yearCode,
                random_int(100000, 999999)
            )
        );
    }

    public function generateStaffId(int $departmentId): string
    {
        $department = Department::with('faculty')->findOrFail($departmentId);
        $year = now()->format('Y');

        return $this->generateUnique(
            'staff_id',
            fn () => sprintf(
                '%s/%s/LEC/%s/%04d',
                $department->faculty->code,
                $department->code,
                $year,
                random_int(1, 9999)
            )
        );
    }

    /**
     * @param  callable(): string  $factory
     */
    private function generateUnique(string $column, callable $factory): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $factory();

            // withTrashed: a soft-deleted user still holds the unique index, so
            // ignoring them would generate identifiers that cannot be inserted.
            $taken = User::withTrashed()->where($column, $candidate)->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Could not generate a unique {$column} after ".self::MAX_ATTEMPTS.' attempts. '
            .'The identifier space for this department may be exhausted.'
        );
    }
}
