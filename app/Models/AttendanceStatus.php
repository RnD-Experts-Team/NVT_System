<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceStatus extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_record_id',
        'shift_assignment_id',
        'attendance_date',
        'status',
        'clock_in',
        'clock_out',
        'late_minutes',
        'early_minutes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fingerprintRecord(): BelongsTo
    {
        return $this->belongsTo(FingerprintRecord::class);
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }
}
