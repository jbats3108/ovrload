<?php

namespace App\Routines\Models;

use App\Shared\Enums\WarmUpWeightMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class RoutineWarmUpStep extends Model
{
    #[Override]
    protected $fillable = [
        'routine_set_group_id',
        'position',
        'weight_mode',
        'percent_of_working',
        'reps',
        'has_setup_after',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weight_mode' => WarmUpWeightMode::class,
            'percent_of_working' => 'integer',
            'reps' => 'integer',
            'has_setup_after' => 'boolean',
        ];
    }

    /** @return BelongsTo<RoutineSetGroup, $this> */
    public function setGroup(): BelongsTo
    {
        return $this->belongsTo(RoutineSetGroup::class, 'routine_set_group_id');
    }
}
