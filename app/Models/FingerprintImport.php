<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FingerprintImport extends Model
{
    protected $fillable = [
        'imported_by',
        'department_id',
        'week_start',
        'filename',
        'status',
        'rows_imported',
        'rows_failed',
        'error_log',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start'   => 'date:Y-m-d',
            'imported_at'  => 'datetime',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(FingerprintRecord::class, 'import_id');
    }
}
