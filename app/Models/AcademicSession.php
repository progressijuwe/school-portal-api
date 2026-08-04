<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cast types are declared here as well as in casts(): static analysis reads
 * these annotations, so without them the date columns are inferred as plain
 * strings and every ->toDateString() call looks like an error.
 *
 * @property int $id
 * @property string $name
 * @property int $start_year
 * @property int $end_year
 * @property Carbon|null $first_semester_start
 * @property Carbon|null $first_semester_end
 * @property Carbon|null $second_semester_start
 * @property Carbon|null $second_semester_end
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CourseOffering> $courseOfferings
 * @property-read int|null $course_offerings_count
 *
 * @method static \Database\Factories\AcademicSessionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereEndYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereFirstSemesterEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereFirstSemesterStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereIsCurrent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereSecondSemesterEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereSecondSemesterStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereStartYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicSession whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_year',
        'end_year',
        'first_semester_start',
        'first_semester_end',
        'second_semester_start',
        'second_semester_end',
        'is_current',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'first_semester_start' => 'date',
            'first_semester_end' => 'date',
            'second_semester_start' => 'date',
            'second_semester_end' => 'date',
        ];
    }

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * Which semester of this session today falls in.
     *
     * Every screen that shows "this semester" previously hardcoded `first`,
     * so from January onward the portal defaulted to showing the wrong
     * half of the year until the user changed the dropdown by hand.
     *
     * Falls back to the first semester when the dates are not configured, or
     * when today sits outside both windows (a vacation period) and the second
     * semester has not started yet.
     */
    public function currentSemester(): Semester
    {
        $today = now()->startOfDay();

        if (
            $this->second_semester_start
            && $today->greaterThanOrEqualTo($this->second_semester_start)
        ) {
            return Semester::Second;
        }

        return Semester::First;
    }

    /**
     * Make this the session every "current" lookup resolves to.
     *
     * Wrapped in a transaction because the two writes are not independent: if
     * the clear succeeded and the set failed, *no* session would be current,
     * and every screen in the portal resolves the current session first — the
     * whole application would answer "No active academic session found" until
     * someone fixed it by hand.
     */
    public function markAsCurrent(): void
    {
        DB::transaction(function () {
            static::where('is_current', true)->update(['is_current' => false]);
            $this->update(['is_current' => true]);
        });
    }
}
