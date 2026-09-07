import { gramsToKg, type PlateLoadResult } from '@/lib/plateCalculator';
import { serializePlateStack } from '@/workouts/lib/plates';
import type { PlayerSet } from '@/workouts/types';

export type CompleteSetPayload =
    | {
          reps?: number | null;
          duration_seconds?: number | null;
          segments: Array<{ weight_kg: number }>;
          is_skipped?: boolean;
      }
    | {
          reps?: number | null;
          duration_seconds?: number | null;
          weight_kg?: number | null;
          plate_stack?: ReturnType<typeof serializePlateStack> | null;
          is_skipped?: boolean;
      };

export type CompleteSetOptions = {
    durationSeconds?: number | null;
    isSkipped?: boolean;
};

/** Mirrors backend `WorkoutSetLogger::applyLoggedValues` input shape for a single set log. */
export function buildCompleteSetPayload(
    set: PlayerSet,
    reps: number | null,
    weightKg: number | null,
    draftSegments: Array<{ weight_kg: number }>,
    plateLoad: PlateLoadResult | null,
    options?: CompleteSetOptions,
): CompleteSetPayload {
    if (options?.isSkipped) {
        return {
            is_skipped: true,
        };
    }

    if (set.is_dropset) {
        const payload: CompleteSetPayload = {
            reps: reps ?? 0,
            segments: draftSegments.map((segment) => ({ weight_kg: segment.weight_kg })),
        };
        if (options?.isSkipped !== undefined) {
            payload.is_skipped = false;
        }
        return payload;
    }

    const finalPlateLoad = plateLoad != null && weightKg != null && gramsToKg(plateLoad.total_g) === weightKg ? plateLoad : null;

    if (set.prescription_mode === 'duration' || options?.durationSeconds != null) {
        const payload: CompleteSetPayload = {
            duration_seconds: options?.durationSeconds ?? set.target_duration_seconds ?? 0,
            weight_kg: weightKg ?? 0,
            plate_stack: finalPlateLoad ? serializePlateStack(finalPlateLoad) : null,
        };
        if (options?.isSkipped !== undefined) {
            payload.is_skipped = false;
        }
        return payload;
    }

    const payload: CompleteSetPayload = {
        reps: reps ?? set.target_reps ?? 0,
        weight_kg: weightKg ?? 0,
        plate_stack: finalPlateLoad ? serializePlateStack(finalPlateLoad) : null,
    };
    if (options?.isSkipped !== undefined) {
        payload.is_skipped = false;
    }
    return payload;
}
