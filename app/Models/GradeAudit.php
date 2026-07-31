<?php

namespace App\Models;

use App\Enums\GradeAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $grade_id
 * @property int|null $actor_id
 * @property GradeAuditAction $action
 * @property array<array-key, mixed>|null $before
 * @property array<array-key, mixed> $after
 * @property string|null $reason
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read Grade $grade
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereGradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeAudit whereReason($value)
 *
 * @mixin \Eloquent
 */
class GradeAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'grade_id',
        'actor_id',
        'action',
        'before',
        'after',
        'reason',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => GradeAuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
