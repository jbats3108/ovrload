<?php

namespace App\Routines\Models;

use App\Shared\Enums\SetGroupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class RoutineSetGroup extends Model
{
    #[Override]
    protected $fillable = [
        'routine_block_id',
        'type',
        'set_count',
        'rest_seconds',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => SetGroupType::class,
            'set_count' => 'integer',
            'rest_seconds' => 'integer',
        ];
    }

    /** @return HasMany<RoutineWarmUpStep, $this> */
    public function warmUpSteps(): HasMany
    {
        return $this->hasMany(RoutineWarmUpStep::class)->orderBy('position');
    }

    /** @return HasMany<RoutineDropsetSegment, $this> */
    public function dropsetSegments(): HasMany
    {
        return $this->hasMany(RoutineDropsetSegment::class)
            ->orderBy('set_index')
            ->orderBy('position');
    }
}
