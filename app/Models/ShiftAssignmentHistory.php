<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignmentHistory extends Model
{
    protected $table = 'shift_assignment_history';

    // Append-only table — no updated_at
    public $timestamps = false;

    protected $fillable = [
        'shift_assignment_id',
        'changed_by',
        'previous_type',
        'previous_shift_id',
        'new_type',
        'new_shift_id',
        'comment',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function previousShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'previous_shift_id');
    }

    public function newShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'new_shift_id');
    }
}
