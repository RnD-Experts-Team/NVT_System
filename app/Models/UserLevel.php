<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLevel extends Model
{
    protected $fillable = [
        'code',
        'name',
        'hierarchy_rank',
        'description',
    ];

    protected $hidden = [
    'created_at',
    'updated_at',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(UserLevelTier::class)->orderBy('tier_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
