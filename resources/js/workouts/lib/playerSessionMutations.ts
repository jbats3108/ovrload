import type { CompleteSetPayload } from '@/workouts/lib/playerSetLog';
import { router, type InertiaForm } from '@inertiajs/vue3';
import type { Ref } from 'vue';

type MutatingRef = Ref<boolean>;

export type SessionMutationOptions = {
    mutating?: MutatingRef;
    onSuccess?: () => void;
    onFinish?: () => void;
    onError?: () => void;
};

type CompleteSetForm = InertiaForm<{
    reps: number;
    weight_kg: number;
    segments: Array<{ weight_kg: number }>;
}>;

const visitOptions = {
    preserveScroll: true,
    only: ['workout'],
};

function beginMutation(options: SessionMutationOptions): boolean {
    if (options.mutating?.value) {
        return false;
    }

    if (options.mutating) {
        options.mutating.value = true;
    }

    return true;
}

function finishMutation(options: SessionMutationOptions): void {
    if (options.mutating) {
        options.mutating.value = false;
    }

    options.onFinish?.();
}

/** Mirrors backend `WorkoutSessionService::completeSet` route visit. */
export function postCompleteSet(
    form: CompleteSetForm,
    workoutId: string,
    setId: number,
    payload: CompleteSetPayload,
    options: Pick<SessionMutationOptions, 'onSuccess' | 'onError'> = {},
): void {
    form.transform(() => payload).post(route('workouts.sets.complete', { workout: workoutId, set: setId }), {
        ...visitOptions,
        onSuccess: options.onSuccess,
        onError: options.onError,
    });
}

/** Mirrors backend `WorkoutSessionService::promoteToDropset`. */
export function promoteToDropset(
    workoutId: string,
    setId: number,
    segments: Array<{ weight_kg: number }>,
    options: SessionMutationOptions = {},
): void {
    if (!beginMutation(options)) {
        return;
    }

    router.post(
        route('workouts.sets.promote-dropset', { workout: workoutId, set: setId }),
        { segments },
        {
            ...visitOptions,
            onSuccess: options.onSuccess,
            onFinish: () => finishMutation(options),
        },
    );
}

/** Mirrors backend `WorkoutSessionService::demoteFromDropset`. */
export function demoteFromDropset(workoutId: string, setId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.post(
        route('workouts.sets.demote-dropset', { workout: workoutId, set: setId }),
        {},
        {
            ...visitOptions,
            onSuccess: options.onSuccess,
            onFinish: () => finishMutation(options),
        },
    );
}

/** Mirrors backend `WorkoutSessionService::addWorkingSet`. */
export function addWorkingSet(workoutId: string, blockId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.post(
        route('workouts.working-sets.add', [workoutId, blockId]),
        {},
        {
            ...visitOptions,
            onSuccess: options.onSuccess,
            onFinish: () => finishMutation(options),
        },
    );
}

/** Mirrors backend `WorkoutSessionService::removeWorkingSetRound`. */
export function removeWorkingSet(workoutId: string, setId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.delete(route('workouts.sets.remove', [workoutId, setId]), {
        ...visitOptions,
        onSuccess: options.onSuccess,
        onFinish: () => finishMutation(options),
    });
}

/** Mirrors backend `WorkoutSessionService::addAdHocExercise`. */
export function addAdHocExercise(workoutId: string, exerciseId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.post(
        route('workouts.ad-hoc-exercises.store', workoutId),
        { exercise_id: exerciseId },
        {
            ...visitOptions,
            onSuccess: options.onSuccess,
            onFinish: () => finishMutation(options),
        },
    );
}

/** Mirrors backend `WorkoutSessionService::removeAdHocBlock`. */
export function removeAdHocBlock(workoutId: string, blockId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.delete(route('workouts.ad-hoc-blocks.destroy', [workoutId, blockId]), {
        ...visitOptions,
        onSuccess: options.onSuccess,
        onFinish: () => finishMutation(options),
    });
}

/** Mirrors backend `WorkoutSessionService::skipRestOfBlock`. */
export function skipRestOfBlock(workoutId: string, blockId: number, options: SessionMutationOptions = {}): void {
    if (!beginMutation(options)) {
        return;
    }

    router.post(
        route('workouts.blocks.skip-rest', [workoutId, blockId]),
        {},
        {
            ...visitOptions,
            onSuccess: options.onSuccess,
            onFinish: () => finishMutation(options),
        },
    );
}
