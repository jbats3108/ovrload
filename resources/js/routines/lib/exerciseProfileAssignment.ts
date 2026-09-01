import type { Block, BlockExercise } from '@/routines/types';
import type { ExerciseProfileOption } from '@/settings/types';

/** Mirrors backend `ExerciseProfileAssignment::expectedExerciseFingerprint` / exercise fingerprint rules. */
export function exerciseAssignmentFingerprint(profile: ExerciseProfileOption, isSuperset: boolean, sharedProfileMatchesExercise = false): string {
    return isSuperset || !sharedProfileMatchesExercise ? profile.exercise_fingerprint : profile.recipe_fingerprint;
}

export function profileMatchesExerciseAssignment(
    exercise: BlockExercise,
    profile: ExerciseProfileOption,
    isSuperset: boolean,
    sharedProfileMatchesExercise = false,
): boolean {
    return (
        exercise.exercise_profile_id === profile.id &&
        exercise.exercise_profile_fingerprint === exerciseAssignmentFingerprint(profile, isSuperset, sharedProfileMatchesExercise)
    );
}

export function profileMatchesSharedAssignment(block: Block, profile: ExerciseProfileOption): boolean {
    return block.shared_profile_id === profile.id && block.shared_profile_fingerprint === profile.shared_fingerprint;
}
