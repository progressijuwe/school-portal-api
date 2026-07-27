<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'building',
        'code',
        'type',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity'  => 'integer',
    ];

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }
}
