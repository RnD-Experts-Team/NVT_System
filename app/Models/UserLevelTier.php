<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLevelTier extends Model
{
    protected $fillable = [
        'user_level_id',
        'tier_name',
        'tier_order',
        'description',
    ];

    protected $hidden = [
    'created_at',
    'updated_at',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class, 'user_level_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
