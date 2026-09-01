<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class PlateProfileBar extends Model
{
    #[Override]
    protected $fillable = [
        'plate_profile_id',
        'name',
        'weight_g',
        'is_default',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'weight_g' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
