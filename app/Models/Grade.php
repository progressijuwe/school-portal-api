<?php

namespace App\Models;

use App\Enums\GradeStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $submitted_by
 * @property int|null $approved_by
 * @property numeric $score
 * @property int|null $ca_score
 * @property int|null $project_score
 * @property int|null $exam_score
 * @property string|null $letter_grade
 * @property numeric|null $grade_point
 * @property GradeStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read Collection<int, GradeAudit> $audits
 * @property-read int|null $audits_count
 * @property-read Enrollment $enrollment
 * @property-read User|null $submittedBy
 *
 * @method static \Database\Factories\GradeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereCaScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereExamScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereGradePoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereLetterGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereProjectScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereSubmittedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grade whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'submitted_by',
        'approved_by',
        'ca_score',
        'project_score',
        'exam_score',
        'score',
        'letter_grade',
        'grade_point',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    /**
     * The component columns are unsignedTinyInteger, so they are cast — and
     * validated — as integers. Casting a `numeric` input to integer here is what
     * previously truncated a submitted 17.5 to 17 without telling anyone.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GradeStatus::class,
            'score' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'ca_score' => 'integer',
            'project_score' => 'integer',
            'exam_score' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<GradeAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(GradeAudit::class);
    }
}
