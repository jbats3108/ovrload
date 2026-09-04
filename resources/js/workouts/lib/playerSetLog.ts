import { gramsToKg, type PlateLoadResult } from '@/lib/plateCalculator';
import { serializePlateStack } from '@/workouts/lib/plates';
import type { PlayerSet } from '@/workouts/types';

export type CompleteSetPayload =
    | {
          reps: number;
          segments: Array<{ weight_kg: number }>;
      }
    | {
          reps: number;
          weight_kg: number;
          plate_stack: ReturnType<typeof serializePlateStack> | null;
      };

/** Mirrors backend `WorkoutSetLogger::applyLoggedValues` input shape for a single set log. */
export function buildCompleteSetPayload(
    set: PlayerSet,
    reps: number,
    weightKg: number,
    draftSegments: Array<{ weight_kg: number }>,
    plateLoad: PlateLoadResult | null,
): CompleteSetPayload {
    if (set.is_dropset) {
        return {
            reps,
            segments: draftSegments.map((segment) => ({ weight_kg: segment.weight_kg })),
        };
    }

    const finalPlateLoad = plateLoad != null && weightKg != null && gramsToKg(plateLoad.total_g) === weightKg ? plateLoad : null;

    return {
        reps,
        weight_kg: weightKg,
        plate_stack: finalPlateLoad ? serializePlateStack(finalPlateLoad) : null,
    };
}
