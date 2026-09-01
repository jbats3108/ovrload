<?php

namespace App\Users\Services;

use App\Users\Data\UpsertPlateProfileData;
use App\Users\Models\PlateProfile;
use App\Users\Models\PlateProfileBar;
use App\Users\Models\PlateProfilePlate;
use App\Users\Models\User;
use Illuminate\Support\Facades\DB;

class PlateProfileService
{
    /**
     * @return array{
     *     name: string,
     *     bars: list<array{name: string, weight_g: int, is_default: bool}>,
     *     plates: list<array{denomination_g: int, count: int, colour: ?string}>
     * }
     */
    public function profilePayloadFor(User $user): array
    {
        $profile = $this->ensureProfile($user);
        $profile->load(['bars', 'plates']);

        return [
            'name' => $profile->name,
            'bars' => array_values($profile->bars
                ->map(fn (PlateProfileBar $bar): array => [
                    'name' => $bar->name,
                    'weight_g' => $bar->weight_g,
                    'is_default' => $bar->is_default,
                ])
                ->all()),
            'plates' => array_values($profile->plates
                ->sortByDesc('denomination_g')
                ->map(fn (PlateProfilePlate $plate): array => [
                    'denomination_g' => $plate->denomination_g,
                    'count' => $plate->count,
                    'colour' => $plate->colour,
                ])
                ->all()),
        ];
    }

    public function ensureProfile(User $user): PlateProfile
    {
        $existing = $user->plateProfile()->with(['bars', 'plates'])->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user): PlateProfile {
            // Serialize create-per-user; unique is (user_id, name) so a lock is required.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = $user->plateProfile()->with(['bars', 'plates'])->first();
            if ($existing !== null) {
                return $existing;
            }

            $defaults = PlateCalculatorService::defaultProfilePayload();
            $profile = PlateProfile::create([
                'user_id' => $user->id,
                'name' => $defaults['name'],
            ]);

            foreach ($defaults['bars'] as $bar) {
                PlateProfileBar::create([
                    'plate_profile_id' => $profile->id,
                    'name' => $bar['name'],
                    'weight_g' => $bar['weight_g'],
                    'is_default' => $bar['is_default'],
                ]);
            }

            foreach ($defaults['plates'] as $plate) {
                PlateProfilePlate::create([
                    'plate_profile_id' => $profile->id,
                    'denomination_g' => $plate['denomination_g'],
                    'count' => $plate['count'],
                    'colour' => $plate['colour'],
                ]);
            }

            return $profile->load(['bars', 'plates']);
        });
    }

    public function upsert(User $user, UpsertPlateProfileData $data): PlateProfile
    {
        return DB::transaction(function () use ($user, $data): PlateProfile {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $profile = $user->plateProfile()->first();
            if ($profile === null) {
                $profile = PlateProfile::create([
                    'user_id' => $user->id,
                    'name' => $data->name,
                ]);
            } else {
                $profile->update(['name' => $data->name]);
            }

            $profile->bars()->delete();
            $profile->plates()->delete();

            $sawDefault = false;
            foreach ($data->bars as $bar) {
                $isDefault = $bar->isDefault && ! $sawDefault;
                if ($isDefault) {
                    $sawDefault = true;
                }
                PlateProfileBar::create([
                    'plate_profile_id' => $profile->id,
                    'name' => $bar->name,
                    'weight_g' => $bar->weightG,
                    'is_default' => $isDefault,
                ]);
            }

            if (! $sawDefault && $data->bars->count() > 0) {
                $first = $profile->bars()->orderBy('id')->first();
                if ($first !== null) {
                    $first->update(['is_default' => true]);
                }
            }

            foreach ($data->plates as $plate) {
                PlateProfilePlate::create([
                    'plate_profile_id' => $profile->id,
                    'denomination_g' => $plate->denominationG,
                    'count' => $plate->count,
                    'colour' => $plate->colour,
                ]);
            }

            return $profile->fresh(['bars', 'plates']) ?? $profile;
        });
    }

    public function defaultBarWeightG(User $user): ?int
    {
        $profile = $this->ensureProfile($user);
        $bar = $profile->bars->firstWhere('is_default', true) ?? $profile->bars->first();

        return $bar?->weight_g;
    }
}
