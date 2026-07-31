<?php

namespace Tests\Unit;

use App\Services\GradeService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeServiceTest extends TestCase
{
    private GradeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GradeService;
    }

    #[DataProvider('bandBoundaries')]
    public function test_it_maps_a_score_to_the_correct_band(float $score, string $letter, float $points): void
    {
        $resolved = $this->service->resolveGrade($score);

        $this->assertSame($letter, $resolved['letter_grade'], "Score {$score}");
        $this->assertSame($points, $resolved['grade_point'], "Score {$score}");
    }

    /**
     * Every boundary, from both sides.
     *
     * @return array<string, array{0: float, 1: string, 2: float}>
     */
    public static function bandBoundaries(): array
    {
        return [
            'perfect' => [100.0, 'A', 4.00],
            'A lower bound' => [95.0, 'A', 4.00],
            'just below A' => [94.0, 'A-', 3.75],
            'A- lower bound' => [89.0, 'A-', 3.75],
            'just below A-' => [88.0, 'B+', 3.25],
            'B+ lower bound' => [83.0, 'B+', 3.25],
            'B lower bound' => [77.0, 'B', 3.00],
            'B- lower bound' => [71.0, 'B-', 2.75],
            'C+ lower bound' => [65.0, 'C+', 2.25],
            'C lower bound' => [59.0, 'C', 2.00],
            'C- lower bound' => [53.0, 'C-', 1.75],
            'D lower bound' => [48.0, 'D', 1.00],
            'just below D' => [47.0, 'F', 0.00],
            'zero' => [0.0, 'F', 0.00],
        ];
    }

    /**
     * The original band table carried explicit min/max pairs with gaps between
     * them — A- topped out at 94 and A began at 95, so 94.5 matched no band and
     * fell through to the F fallback at the bottom of the loop.
     */
    #[DataProvider('fractionalScoresInFormerGaps')]
    public function test_fractional_scores_in_the_former_band_gaps_no_longer_fall_through(
        float $score,
        string $letter,
    ): void {
        $this->assertSame($letter, $this->service->resolveGrade($score)['letter_grade']);
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function fractionalScoresInFormerGaps(): array
    {
        return [
            '94.5 is an A-' => [94.5, 'A-'],
            '88.5 is a B+' => [88.5, 'B+'],
            '82.5 is a B' => [82.5, 'B'],
            '76.5 is a B-' => [76.5, 'B-'],
            '70.5 is a C+' => [70.5, 'C+'],
            '64.5 is a C' => [64.5, 'C'],
            '58.5 is a C-' => [58.5, 'C-'],
            '52.5 is a D' => [52.5, 'D'],
            '47.5 is an F' => [47.5, 'F'],
        ];
    }

    public function test_it_rejects_a_score_above_the_maximum(): void
    {
        // Failing loudly beats silently recording something wrong on a transcript.
        $this->expectException(InvalidArgumentException::class);

        $this->service->resolveGrade(101);
    }

    public function test_it_rejects_a_negative_score(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->resolveGrade(-1);
    }
}
