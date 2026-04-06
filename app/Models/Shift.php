<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'is_overnight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function coverAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'cover_shift_id');
    }
}
