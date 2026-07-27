<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
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

    protected $casts = [
        'is_current'             => 'boolean',
        'first_semester_start'   => 'date',
        'first_semester_end'     => 'date',
        'second_semester_start'  => 'date',
        'second_semester_end'    => 'date',
    ];

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function markAsCurrent(): void
    {
        static::where('is_current', true)->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }
}