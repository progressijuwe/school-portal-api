<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GradeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Replays a student's full GPA history after any change to their approved
 * grades.
 *
 * Queued because it touches every semester the student has ever taken, which
 * does not belong in the request cycle of a single grade approval. Unique per
 * student so a bulk approval of thirty grades collapses into one recompute
 * instead of thirty.
 */
class RecomputeStudentGpa implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Ceiling on the uniqueness lock, so a crashed worker cannot block future
     * recomputes for this student indefinitely.
     */
    public int $uniqueFor = 300;

    public function __construct(public int $studentId) {}

    public function uniqueId(): string
    {
        return (string) $this->studentId;
    }

    public function handle(GradeService $grades): void
    {
        $grades->recomputeStudentHistory($this->studentId);
    }
}
