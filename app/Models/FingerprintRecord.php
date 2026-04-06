<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintRecord extends Model
{
    protected $fillable = [
        'import_id',
        'user_id',
        'record_date',
        'clock_in',
        'clock_out',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date:Y-m-d',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(FingerprintImport::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
