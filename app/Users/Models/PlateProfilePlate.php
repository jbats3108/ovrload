<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class PlateProfilePlate extends Model
{
    #[Override]
    protected $fillable = [
        'plate_profile_id',
        'denomination_g',
        'count',
        'colour',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'denomination_g' => 'integer',
            'count' => 'integer',
        ];
    }
}
