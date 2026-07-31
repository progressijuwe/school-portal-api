<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $prefix
 * @property string $highest_qualification
 * @property string|null $specialization
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereHighestQualification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile wherePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereSpecialization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LecturerProfile whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LecturerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prefix',
        'highest_qualification',
        'specialization',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
