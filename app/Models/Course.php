<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'department_id',
        'title',
        'code',
        'credit_units',
        'level',
        'semester',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'credit_units' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}