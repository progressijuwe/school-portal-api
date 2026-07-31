<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Department> $departments
 * @property-read int|null $departments_count
 *
 * @method static \Database\Factories\FacultyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Faculty extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
