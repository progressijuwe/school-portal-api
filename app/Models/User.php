<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $profile_photo_url
 * @property string|null $profile_photo_public_id
 * @property string|null $student_id
 * @property string|null $staff_id
 * @property int|null $department_id
 * @property string|null $study_type
 * @property int|null $entry_year
 * @property bool $must_change_password
 * @property Carbon|null $password_reset_requested_at
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Department|null $department
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read Collection<int, GpaRecord> $gpaRecords
 * @property-read int|null $gpa_records_count
 * @property-read LecturerProfile|null $lecturerProfile
 * @property-read string|null $level
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read UserProfile|null $profile
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, CourseOffering> $taughtOfferings
 * @property-read int|null $taught_offerings_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static Builder<static>|User atLevel(int $level)
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User permission($permissions, $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereDepartmentId($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereEntryYear($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereMustChangePassword($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereProfilePhotoPublicId($value)
 * @method static Builder<static>|User whereProfilePhotoUrl($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereStaffId($value)
 * @method static Builder<static>|User whereStudentId($value)
 * @method static Builder<static>|User whereStudyType($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, $guard = null)
 * @method static Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_url',
        'profile_photo_public_id',
        'student_id',
        'staff_id',
        'department_id',
        'study_type',
        'entry_year',
        'must_change_password',
        'password_reset_requested_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_reset_requested_at' => 'datetime',
            // A YEAR column comes back from MySQL as a string, so arithmetic
            // like `entry_year + duration_years` relied on PHP's numeric-string
            // coercion. Casting makes it an int everywhere, including in the
            // JSON the frontend consumes.
            'entry_year' => 'integer',
        ];
    }

    /**
     * Academic level ('100'..'500') derived from entry year.
     *
     * Anchored to the current academic session's start year rather than
     * now()->year — the calendar year rolls over in the middle of the first
     * semester, which would promote every student on 1 January.
     */
    protected function level(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->entry_year === null) {
                return null;
            }

            $startYear = (int) (AcademicSession::where('is_current', true)->value('start_year')
                ?? now()->year);

            $level = (min(max($startYear - (int) $this->entry_year, 0), 4) + 1) * 100;

            return (string) $level;
        })->shouldCache();
    }

    /**
     * The entry-year comparison that defines an academic level.
     *
     * Anchored to the current session's start year for the same reason as the
     * level accessor. The top level is a catch-all: anyone who entered four or
     * more years ago is still finishing.
     *
     * Exposed as a plain static rather than only a scope because the admin
     * registration queue filters students through a *joined* enrollments query,
     * where a User scope is not callable — scopes only resolve on their own
     * model's builder. Both call sites read the rule from here so it cannot
     * drift.
     *
     * @return array{0: string, 1: int} [operator, entry year]
     */
    public static function levelConstraint(int $level): array
    {
        $startYear = (int) (AcademicSession::where('is_current', true)->value('start_year')
            ?? now()->year);

        return $level >= 500
            ? ['<=', $startYear - 4]
            : ['=', $startYear - (intdiv($level, 100) - 1)];
    }

    /**
     * Restrict to students at a given academic level ('100'..'500').
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAtLevel(Builder $query, int $level): Builder
    {
        [$operator, $entryYear] = self::levelConstraint($level);

        return $query->where('users.entry_year', $operator, $entryYear);
    }

    /**
     * Relations carry generics so static analysis can see through them —
     * without the type parameters every `$user->lecturerProfile->prefix`
     * resolves to a bare Model and looks like an undefined property.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * @return HasOne<LecturerProfile, $this>
     */
    public function lecturerProfile(): HasOne
    {
        return $this->hasOne(LecturerProfile::class);
    }

    /**
     * Course offerings this user teaches. Only meaningful for lecturers, but
     * defined here so ownership checks can run without a role branch.
     */
    public function taughtOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class, 'lecturer_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function gpaRecords(): HasMany
    {
        return $this->hasMany(GpaRecord::class, 'student_id');
    }
}
