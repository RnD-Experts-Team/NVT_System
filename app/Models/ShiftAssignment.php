<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftAssignment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'weekly_schedule_id',
        'user_id',
        'assignment_date',
        'assignment_type',
        'shift_id',
        'is_cover',
        'cover_for_user_id',
        'cover_shift_id',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'assignment_date' => 'date:Y-m-d',
            'is_cover'        => 'boolean',
        ];
    }

    public function weeklySchedule(): BelongsTo
    {
        return $this->belongsTo(WeeklySchedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function coverForUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cover_for_user_id');
    }

    public function coverShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'cover_shift_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ShiftAssignmentHistory::class)->orderBy('changed_at', 'asc');
    }
}
