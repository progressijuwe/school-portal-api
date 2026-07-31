<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_offering_id
 * @property int $venue_id
 * @property DayOfWeek $day
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseOffering $courseOffering
 * @property-read Venue|null $venue
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereCourseOfferingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimetableSlot whereVenueId($value)
 *
 * @mixin \Eloquent
 */
class TimetableSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'venue_id',
        'day',
        'start_time',
        'end_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => DayOfWeek::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
