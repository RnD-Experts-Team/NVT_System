<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklySchedule extends Model
{
    protected $fillable = [
        'department_id',
        'week_start',
        'status',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start'   => 'date:Y-m-d',
            'published_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function scopeForWeek(Builder $query, string $weekStart): Builder
    {
        return $query->where('week_start', $weekStart);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}
