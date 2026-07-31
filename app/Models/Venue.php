<?php

namespace App\Models;

use App\Enums\VenueType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $building
 * @property string $code
 * @property VenueType $type
 * @property int $capacity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TimetableSlot> $timetableSlots
 * @property-read int|null $timetable_slots_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereBuilding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'building',
        'code',
        'type',
        'capacity',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VenueType::class,
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    /**
     * @return HasMany<TimetableSlot, $this>
     */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }
}
