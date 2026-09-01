<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class PlateProfile extends Model
{
    #[Override]
    protected $fillable = [
        'user_id',
        'name',
    ];

    /** @return HasMany<PlateProfileBar, $this> */
    public function bars(): HasMany
    {
        return $this->hasMany(PlateProfileBar::class);
    }

    /** @return HasMany<PlateProfilePlate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(PlateProfilePlate::class);
    }
}
