import { gramsToKg, type PlateLoadResult } from '@/lib/plateCalculator';
import type { EquipmentOption, ExerciseOption, MuscleGroupOption } from '@/routines/types';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { hapticConfirm, hapticTap } from '@/shared/lib/haptics';
import { findFirstIncompleteFocus, flattenPlayerSets, setupKey, type FlatSetEntry } from '@/workouts/lib/focus';
import { formatRestSeconds, groupLabel, setupHintText, workoutProgressLabel } from '@/workouts/lib/labels';
import { bumpedWeightKg, qualifiesForMidBlockBump, workingSetPrefillKg, type MidBlockBumpOffer } from '@/workouts/lib/midBlockBump';
import { changePlateCount, formatPlateStackLabel, resolvePlateLoad, resolvePlateStack, serializePlateStack } from '@/workouts/lib/plates';
import { preparePlayerInteraction } from '@/workouts/lib/playerInteraction';
import {
    addAdHocExercise as addAdHocExerciseMutation,
    addWorkingSet as addWorkingSetMutation,
    demoteFromDropset as demoteFromDropsetMutation,
    postCompleteSet,
    promoteToDropset as promoteToDropsetMutation,
    removeAdHocBlock as removeAdHocBlockMutation,
    removeWorkingSet as removeWorkingSetMutation,
    skipRestOfBlock as skipRestOfBlockMutation,
} from '@/workouts/lib/playerSessionMutations';
import { buildCompleteSetPayload } from '@/workouts/lib/playerSetLog';
import { notifyRestCountdown, notifyRestEnded, shouldBeepRestCountdown } from '@/workouts/lib/restAlert';
import { releaseScreenWake, requestScreenWake } from '@/workouts/lib/screenWake';
import {
    defaultPromoteSegments,
    finishesWarmUpGroup,
    finishesWarmUpStep,
    nextDropSegmentWeight,
    nextSupersetSet,
    plannedSetCount,
    previousSetWeightKg,
    shouldRestAfter,
    supersetRoundSets,
    visitLeavesWorkout,
    warmUpRestSeconds,
    workingRestSeconds,
    workingRoundsInBlock,
    workingWeightForSet,
} from '@/workouts/lib/sets';
import { abandonWorkout as abandonWorkoutMutation, finishWorkout as finishWorkoutMutation } from '@/workouts/lib/workoutMutations';
import type { Focus, PlateProfile, WorkoutPayload } from '@/workouts/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, inject, onBeforeUnmount, onMounted, ref, watch, type InjectionKey } from 'vue';

export type PlayWorkoutProps = {
    workout: WorkoutPayload;
    plate_profile: PlateProfile;
    exercises?: ExerciseOption[];
    muscle_groups?: MuscleGroupOption[];
    equipment_options?: EquipmentOption[];
};

export type WorkoutPlayer = ReturnType<typeof createWorkoutPlayer>;

export const workoutPlayerKey: InjectionKey<WorkoutPlayer> = Symbol('workoutPlayer');

export function createWorkoutPlayer(props: PlayWorkoutProps) {
    const setupDone = ref<Record<string, boolean>>({});
    const pendingRestSeconds = ref(0);
    const lastWorkingWeightKg = ref<Record<number, number>>({});
    /** Next-set bump prefill (Progressive Overload); cleared when that set is logged. */
    const nextSetBumpPrefillKg = ref<Record<number, number>>({});
    const pendingMidBlockBump = ref<MidBlockBumpOffer | null>(null);
    const draftSegments = ref<Array<{ weight_kg: number }>>([]);
    const restSecondsLeft = ref(0);
    const leaveConfirmed = ref(false);
    const mutating = ref(false);
    const logSheetOpen = ref(false);
    /** Stage "Use nearest" override; cleared when focus moves to another set. */
    const stageWeightOverrideKg = ref<number | null>(null);
    const stagePlateLoadOverrides = ref<Record<number, PlateLoadResult>>({});
    const logPlateLoadDraft = ref<PlateLoadResult | null>(null);
    const exerciseCatalog = computed(() => props.exercises ?? []);
    const muscleGroups = computed(() => props.muscle_groups ?? []);
    const equipmentOptions = computed(() => props.equipment_options ?? []);

    let restTimer: ReturnType<typeof setInterval> | null = null;
    let restEndsAt = 0;
    /** Last whole second that already got a countdown beep (avoids double-fire on visibility sync). */
    let lastCountdownBeepSecond: number | null = null;
    let removeBeforeListener: (() => void) | undefined;
    let removeVisibilityListener: (() => void) | undefined;
    let removeRestVisibilityListener: (() => void) | undefined;

    const flatSets = computed(() => flattenPlayerSets(props.workout.blocks));

    const firstIncomplete = (): Focus => findFirstIncompleteFocus(props.workout.blocks, setupDone.value);

    const focus = ref<Focus>(firstIncomplete());

    const setForm = useForm({
        reps: 0,
        weight_kg: 0,
        segments: [] as Array<{ weight_kg: number }>,
    });

    const clearRest = () => {
        if (restTimer) {
            clearInterval(restTimer);
            restTimer = null;
        }
        restEndsAt = 0;
        restSecondsLeft.value = 0;
        lastCountdownBeepSecond = null;
        removeRestVisibilityListener?.();
        removeRestVisibilityListener = undefined;
    };

    const finishRest = () => {
        if (pendingMidBlockBump.value) {
            void resolvePendingMidBlockBump().then(() => {
                clearRest();
                notifyRestEnded();
                focus.value = firstIncomplete();
            });
            return;
        }

        clearRest();
        notifyRestEnded();
        focus.value = firstIncomplete();
    };

    const syncRestFromClock = () => {
        if (restEndsAt <= 0) {
            return;
        }

        const remaining = Math.ceil((restEndsAt - Date.now()) / 1000);
        if (remaining <= 0) {
            finishRest();
            return;
        }

        restSecondsLeft.value = remaining;

        if (shouldBeepRestCountdown(remaining) && lastCountdownBeepSecond !== remaining) {
            lastCountdownBeepSecond = remaining;
            notifyRestCountdown(remaining);
        }
    };

    const startRest = (seconds: number) => {
        clearRest();
        pendingRestSeconds.value = 0;
        if (seconds <= 0) {
            focus.value = firstIncomplete();
            return;
        }

        restEndsAt = Date.now() + seconds * 1000;
        restSecondsLeft.value = seconds;
        syncRestFromClock();
        restTimer = setInterval(syncRestFromClock, 1000);

        const onRestVisibility = () => {
            if (restEndsAt > 0) {
                syncRestFromClock();
            }
        };
        document.addEventListener('visibilitychange', onRestVisibility);
        removeRestVisibilityListener = () => document.removeEventListener('visibilitychange', onRestVisibility);
    };

    watch(
        () => props.workout,
        () => {
            if (restSecondsLeft.value > 0 || pendingRestSeconds.value > 0) {
                return;
            }
            // Keep focus when add/remove only changes set count — don't jump to another set.
            if (focus.value.kind === 'set') {
                const focused = flatSets.value.find(({ set }) => set.id === (focus.value as { setId: number }).setId);
                if (focused && !focused.set.completed) {
                    return;
                }
            }
            focus.value = firstIncomplete();
        },
        { deep: true },
    );

    const current = computed(() => {
        if (focus.value.kind !== 'set') {
            return null;
        }
        return flatSets.value.find(({ set }) => set.id === (focus.value as { setId: number }).setId) ?? null;
    });

    const currentBlock = computed(() => {
        if (focus.value.kind === 'done') {
            return null;
        }
        return props.workout.blocks[focus.value.blockIndex] ?? null;
    });

    const currentExercise = computed(() => {
        const entry = current.value;
        const block = currentBlock.value;
        if (!entry || !block) {
            return null;
        }

        return block.exercises.find((exercise) => exercise.id === entry.set.workout_block_exercise_id) ?? null;
    });

    /** Floor / Bump for the log sheet — working sets only. Bump is always the prescribed Target (omitted on deload). */
    const logProgressionHints = computed(() => {
        const entry = current.value;
        const exercise = currentExercise.value;
        if (!entry || !exercise || currentBlock.value?.is_ad_hoc || entry.set.group_type !== 'working' || entry.set.is_dropset) {
            return null;
        }

        const parts: string[] = [];
        if (exercise.achievement_floor != null) {
            parts.push(`Floor ${exercise.achievement_floor}.`);
        }
        if (props.workout.mode !== 'deload') {
            parts.push(`Bump @ ${exercise.prescribed_reps}`);
        }

        return parts.length > 0 ? parts.join(' ') : null;
    });

    const previousPlateLoad = (entry: FlatSetEntry): PlateLoadResult | null => {
        const prior = entry.block.sets
            .filter(
                (set) =>
                    set.workout_block_exercise_id === entry.set.workout_block_exercise_id &&
                    set.group_type === 'working' &&
                    set.set_index < entry.set.set_index &&
                    set.completed &&
                    set.logged_weight_kg != null &&
                    set.plate_stack != null,
            )
            .sort((a, b) => b.set_index - a.set_index)[0];

        if (!prior || prior.logged_weight_kg == null || prior.plate_stack == null) {
            return null;
        }

        return (
            stagePlateLoadOverrides.value[prior.id] ??
            resolvePlateStack(prior.logged_weight_kg, prior.equipment, props.plate_profile, prior.plate_stack)
        );
    };

    const plateLoadForEntry = (entry: FlatSetEntry, weightKg: number | null): PlateLoadResult | null => {
        const stageOverride = stagePlateLoadOverrides.value[entry.set.id];
        if (stageOverride) {
            return stageOverride;
        }

        if (entry.set.completed && entry.set.logged_weight_kg != null && entry.set.plate_stack != null) {
            const persisted = resolvePlateStack(entry.set.logged_weight_kg, entry.set.equipment, props.plate_profile, entry.set.plate_stack);
            if (persisted) {
                return persisted;
            }
        }

        return resolvePlateLoad(weightKg, entry.set.equipment, props.plate_profile, previousPlateLoad(entry));
    };

    const normalizePlateLoadForOwnWeight = (entry: FlatSetEntry, load: PlateLoadResult): PlateLoadResult => {
        return resolvePlateStack(gramsToKg(load.total_g), entry.set.equipment, props.plate_profile, serializePlateStack(load)) ?? load;
    };

    const setStagePlateLoad = (entry: FlatSetEntry, load: PlateLoadResult) => {
        const normalized = normalizePlateLoadForOwnWeight(entry, load);
        stagePlateLoadOverrides.value[entry.set.id] = normalized;
        stageWeightOverrideKg.value = gramsToKg(normalized.total_g);
        setForm.weight_kg = gramsToKg(normalized.total_g);
    };

    const workingWeightForEntry = (entry: FlatSetEntry): number => {
        const exerciseId = entry.set.workout_block_exercise_id;

        return workingSetPrefillKg(
            entry.set.logged_weight_kg,
            previousSetWeightKg(entry),
            lastWorkingWeightKg.value[exerciseId],
            entry.set.target_weight_kg,
            nextSetBumpPrefillKg.value[exerciseId],
        );
    };

    const resolvePendingMidBlockBump = async (): Promise<void> => {
        const pending = pendingMidBlockBump.value;
        if (!pending) {
            return;
        }

        pendingMidBlockBump.value = null;
        const accepted = await confirmDialog({
            title: 'Bump next set?',
            description: `Load ${pending.suggestedWeightKg}${props.workout.weight_unit} on the next working set?`,
            confirmLabel: 'Bump',
            cancelLabel: 'Keep weight',
        });

        if (accepted) {
            nextSetBumpPrefillKg.value[pending.exerciseId] = pending.suggestedWeightKg;
        }
    };

    const acceptMidBlockBump = (): void => {
        const pending = pendingMidBlockBump.value;
        if (!pending) {
            return;
        }

        nextSetBumpPrefillKg.value[pending.exerciseId] = pending.suggestedWeightKg;
        pendingMidBlockBump.value = null;
    };

    const declineMidBlockBump = (): void => {
        pendingMidBlockBump.value = null;
    };

    const applyMidBlockBumpAfterLog = async (
        block: (typeof props.workout.blocks)[number],
        set: (typeof block.sets)[number],
        loggedWeightKg: number,
        loggedReps: number,
        restAfter: number,
    ): Promise<void> => {
        const exercise = block.exercises.find((row) => row.id === set.workout_block_exercise_id);
        if (!exercise) {
            return;
        }

        delete nextSetBumpPrefillKg.value[set.workout_block_exercise_id];

        if (
            !qualifiesForMidBlockBump(exercise, loggedWeightKg, loggedReps, {
                mode: props.workout.mode,
                progressionStyle: props.workout.progression_style,
                blockIsAdHoc: block.is_ad_hoc,
                isDropset: set.is_dropset,
                groupType: set.group_type,
            })
        ) {
            return;
        }

        const suggestedWeightKg = bumpedWeightKg(loggedWeightKg);

        if (props.workout.progressive_mid_block === 'auto') {
            nextSetBumpPrefillKg.value[set.workout_block_exercise_id] = suggestedWeightKg;
            return;
        }

        pendingMidBlockBump.value = {
            exerciseId: set.workout_block_exercise_id,
            suggestedWeightKg,
        };

        if (restAfter === 0) {
            await resolvePendingMidBlockBump();
        }
    };

    const syncDraftFromSet = (entry: FlatSetEntry) => {
        setForm.reps = entry.set.logged_reps ?? entry.set.target_reps ?? 0;
        logPlateLoadDraft.value = null;
        if (entry.set.is_dropset) {
            draftSegments.value =
                entry.set.segments.length >= 2
                    ? entry.set.segments.map((s) => ({ weight_kg: s.weight_kg }))
                    : defaultPromoteSegments(workingWeightForSet(entry));
            setForm.weight_kg = draftSegments.value[0]?.weight_kg ?? 0;
            return;
        }
        draftSegments.value = [];
        if (stageWeightOverrideKg.value != null) {
            const load = plateLoadForEntry(entry, stageWeightOverrideKg.value);
            setForm.weight_kg = load ? gramsToKg(load.total_g) : stageWeightOverrideKg.value;
            logPlateLoadDraft.value = load;
            return;
        }
        if (entry.set.group_type === 'warm_up') {
            setForm.weight_kg = entry.set.logged_weight_kg ?? entry.set.target_weight_kg ?? 0;
            return;
        }
        const weightKg = workingWeightForEntry(entry);
        const load = plateLoadForEntry(entry, weightKg);
        setForm.weight_kg = load ? gramsToKg(load.total_g) : weightKg;
        logPlateLoadDraft.value = load;
    };

    watch(
        current,
        (entry, previous) => {
            logSheetOpen.value = false;
            if (entry?.set.id !== previous?.set.id) {
                stageWeightOverrideKg.value = null;
            }
            if (!entry) {
                return;
            }
            syncDraftFromSet(entry);
        },
        { immediate: true },
    );

    const progressLabel = computed(() => workoutProgressLabel(flatSets.value));
    const restLabel = computed(() => formatRestSeconds(restSecondsLeft.value));

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
        if (props.workout.status !== 'in_progress') {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    };

    const requestWakeLock = async () => {
        await requestScreenWake();
    };

    const releaseWakeLock = async () => {
        await releaseScreenWake();
    };

    onBeforeUnmount(() => {
        clearRest();
        removeBeforeListener?.();
        removeVisibilityListener?.();
        window.removeEventListener('beforeunload', onBeforeUnload);
        void releaseWakeLock();
    });

    onMounted(() => {
        focus.value = firstIncomplete();
        window.addEventListener('beforeunload', onBeforeUnload);
        void requestWakeLock();
        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                void requestWakeLock();
            }
        };
        document.addEventListener('visibilitychange', onVisibility);
        removeVisibilityListener = () => document.removeEventListener('visibilitychange', onVisibility);
        removeBeforeListener = router.on('before', (event) => {
            if (leaveConfirmed.value) {
                return;
            }
            if (props.workout.status !== 'in_progress') {
                return;
            }
            if (!visitLeavesWorkout(event.detail.visit, props.workout.id)) {
                return;
            }

            event.preventDefault();
            const visit = event.detail.visit;
            void confirmDialog({
                title: 'Leave workout?',
                description: 'Progress is saved — you can resume from the dashboard.',
                confirmLabel: 'Leave',
            }).then((ok) => {
                if (!ok) {
                    return;
                }
                leaveConfirmed.value = true;
                router.visit(visit.url, {
                    method: visit.method,
                    data: visit.data,
                    replace: visit.replace,
                    preserveScroll: visit.preserveScroll,
                    preserveState: visit.preserveState,
                    only: visit.only,
                    except: visit.except,
                    headers: visit.headers,
                });
            });
        });
    });

    const leaveWorkout = async () => {
        if (props.workout.status === 'in_progress') {
            const ok = await confirmDialog({
                title: 'Leave workout?',
                description: 'Progress is saved — you can resume from the dashboard.',
                confirmLabel: 'Leave',
            });
            if (!ok) {
                return;
            }
        }
        leaveConfirmed.value = true;
        router.visit(route('dashboard'));
    };

    const openLogSheet = () => {
        if (!current.value || props.workout.status !== 'in_progress') {
            return;
        }
        preparePlayerInteraction();
        hapticTap();
        syncDraftFromSet(current.value);
        logSheetOpen.value = true;
    };

    const cancelLogSheet = () => {
        logPlateLoadDraft.value = null;
        logSheetOpen.value = false;
    };

    const handleLogWeightInput = () => {
        const entry = current.value;
        const weightKg = setForm.weight_kg;
        if (!entry || entry.set.is_dropset || entry.set.group_type !== 'working' || weightKg == null || Number.isNaN(weightKg)) {
            return;
        }

        logPlateLoadDraft.value = resolvePlateLoad(
            weightKg,
            entry.set.equipment,
            props.plate_profile,
            logPlateLoadDraft.value ?? previousPlateLoad(entry),
        );
    };

    const changeLogPlate = (denominationG: number, change: 1 | -1) => {
        const entry = current.value;
        const load = logPlateLoadDraft.value;
        const weightKg = setForm.weight_kg;
        if (!entry || !load || weightKg == null || entry.set.is_dropset) {
            return;
        }

        const next = changePlateCount(weightKg, load, denominationG, change, props.plate_profile);
        if (!next) {
            return;
        }

        const normalized = normalizePlateLoadForOwnWeight(entry, next);
        logPlateLoadDraft.value = normalized;
        setForm.weight_kg = gramsToKg(normalized.total_g);
    };

    const completeSet = () => {
        if (!current.value || props.workout.status !== 'in_progress' || !logSheetOpen.value) {
            return;
        }
        preparePlayerInteraction();
        hapticConfirm();
        logSheetOpen.value = false;
        const { block, set } = current.value;
        let restAfter = shouldRestAfter(block, set) ? set.rest_seconds : 0;
        if (restAfter > 0 && block.has_setup_after_warm_up && finishesWarmUpGroup(block, set)) {
            restAfter = 0;
        }
        if (restAfter > 0 && finishesWarmUpStep(block, set)) {
            restAfter = 0;
        }

        const finalPlateLoad =
            !set.is_dropset && set.group_type === 'working' && logPlateLoadDraft.value?.exact && setForm.weight_kg != null
                ? gramsToKg(logPlateLoadDraft.value.total_g) === setForm.weight_kg
                    ? logPlateLoadDraft.value
                    : null
                : null;
        const payload = buildCompleteSetPayload(set, setForm.reps, setForm.weight_kg, draftSegments.value, finalPlateLoad);
        const loggedReps = setForm.reps;
        const loggedWeightKg = setForm.weight_kg;

        pendingRestSeconds.value = restAfter;

        postCompleteSet(setForm, props.workout.id, set.id, payload, {
            onSuccess: () => {
                if (!set.is_dropset && set.group_type === 'working' && typeof loggedWeightKg === 'number') {
                    lastWorkingWeightKg.value[set.workout_block_exercise_id] = loggedWeightKg;
                }
                if (finalPlateLoad) {
                    stagePlateLoadOverrides.value[set.id] = finalPlateLoad;
                }
                logPlateLoadDraft.value = null;

                const afterLog = async (): Promise<void> => {
                    if (!set.is_dropset && set.group_type === 'working' && typeof loggedReps === 'number' && typeof loggedWeightKg === 'number') {
                        await applyMidBlockBumpAfterLog(block, set, loggedWeightKg, loggedReps, restAfter);
                    }

                    if (restAfter > 0) {
                        startRest(restAfter);
                    } else {
                        pendingRestSeconds.value = 0;
                        focus.value = firstIncomplete();
                    }
                };

                void afterLog();
            },
            onError: () => {
                pendingRestSeconds.value = 0;
            },
        });
    };

    const addDropSegment = () => {
        const last = draftSegments.value[draftSegments.value.length - 1]?.weight_kg ?? 10;
        draftSegments.value.push({ weight_kg: nextDropSegmentWeight(last) });
    };

    const removeDropSegment = (index: number) => {
        if (draftSegments.value.length <= 2) {
            return;
        }
        draftSegments.value.splice(index, 1);
    };

    const canPromoteToDropset = computed(
        () =>
            props.workout.status === 'in_progress' &&
            current.value !== null &&
            current.value.set.group_type === 'working' &&
            !current.value.set.completed &&
            !current.value.set.is_dropset &&
            !current.value.block.is_superset,
    );

    const canDemoteFromDropset = computed(
        () =>
            props.workout.status === 'in_progress' &&
            current.value !== null &&
            current.value.set.group_type === 'working' &&
            !current.value.set.completed &&
            current.value.set.is_dropset,
    );

    const promoteToDropset = () => {
        if (!current.value || !canPromoteToDropset.value) {
            return;
        }
        const entry = current.value;
        promoteToDropsetMutation(props.workout.id, entry.set.id, defaultPromoteSegments(workingWeightForSet(entry)), { mutating });
    };

    const demoteFromDropset = () => {
        if (!current.value || !canDemoteFromDropset.value) {
            return;
        }
        const entry = current.value;
        demoteFromDropsetMutation(props.workout.id, entry.set.id, { mutating });
    };

    const skipRest = () => {
        preparePlayerInteraction();
        if (pendingMidBlockBump.value) {
            void resolvePendingMidBlockBump().then(() => {
                clearRest();
                focus.value = firstIncomplete();
            });
            return;
        }

        clearRest();
        focus.value = firstIncomplete();
    };

    const acknowledgeSetup = () => {
        if (focus.value.kind !== 'setup') {
            return;
        }
        preparePlayerInteraction();
        const phase = focus.value.phase;
        const block = props.workout.blocks[focus.value.blockIndex];
        setupDone.value[setupKey(block.id, phase, focus.value.warmUpStepIndex)] = true;

        if (phase === 'after_warm_up') {
            const rest = workingRestSeconds(block);
            if (rest > 0) {
                startRest(rest);
                return;
            }
        }

        if (phase === 'after_warm_up_step') {
            const rest = warmUpRestSeconds(block);
            if (rest > 0) {
                startRest(rest);
                return;
            }
        }

        focus.value = firstIncomplete();
    };

    const setupHint = computed(() => setupHintText(focus.value, currentBlock.value));

    const supersetNext = computed(() => {
        if (!current.value) {
            return null;
        }

        const nextSet = nextSupersetSet(current.value.block, current.value.set);
        if (!nextSet) {
            return null;
        }

        const entry = { blockIndex: current.value.blockIndex, block: current.value.block, set: nextSet };
        let weightKg: number | null = null;

        if (nextSet.group_type === 'warm_up') {
            weightKg = nextSet.target_weight_kg;
        } else {
            weightKg = workingWeightForEntry(entry);
        }

        const targetParts: string[] = [];
        if (weightKg != null) {
            targetParts.push(`${weightKg}${props.workout.weight_unit}`);
        }
        if (nextSet.target_reps != null) {
            targetParts.push(`× ${nextSet.target_reps}`);
        }

        return {
            exerciseName: nextSet.exercise_name,
            targetLabel: targetParts.length > 0 ? targetParts.join(' ') : null,
            label: targetParts.length > 0 ? `Then: ${nextSet.exercise_name} (${targetParts.join(' ')})` : `Then: ${nextSet.exercise_name}`,
        };
    });

    const previewForEntry = (entry: FlatSetEntry, letter: string | null = null) => {
        let weightKg: number | null = null;
        let weightLabel: string | null = null;
        let plateStack: string | null = null;

        if (entry.set.is_dropset) {
            const segments =
                entry.set.segments.length >= 2
                    ? entry.set.segments.map((s) => s.weight_kg)
                    : defaultPromoteSegments(workingWeightForSet(entry)).map((s) => s.weight_kg);
            weightLabel = segments.join(' → ');
            weightKg = segments[0] ?? null;
        } else if (entry.set.group_type === 'warm_up') {
            weightKg = entry.set.target_weight_kg;
            weightLabel = weightKg != null ? String(weightKg) : null;
        } else {
            weightKg = workingWeightForEntry(entry);
            const load = plateLoadForEntry(entry, weightKg);
            if (load) {
                weightKg = gramsToKg(load.total_g);
                plateStack = formatPlateStackLabel(load, props.workout.weight_unit);
            }
            weightLabel = weightKg != null ? String(weightKg) : null;
        }

        return {
            exerciseName: entry.set.exercise_name,
            groupLabel: groupLabel(entry.set.group_type),
            setNumber: entry.set.set_index + 1,
            setCount: plannedSetCount(entry.block, entry.set),
            blockPosition: entry.block.position,
            weightLabel,
            reps: entry.set.target_reps,
            isDropset: entry.set.is_dropset,
            plateStack,
            letter,
        };
    };

    const upcoming = computed(() => {
        const entry = flatSets.value.find(({ set }) => !set.completed) ?? null;
        if (!entry) {
            return null;
        }

        return previewForEntry(entry);
    });

    /** Both exercises in the upcoming superset round — only while on Setup. */
    const setupSupersetPair = computed(() => {
        if (focus.value.kind !== 'setup') {
            return null;
        }

        const entry = flatSets.value.find(({ set }) => !set.completed) ?? null;
        if (!entry?.block.is_superset) {
            return null;
        }

        const round = supersetRoundSets(entry.block, entry.set);
        if (round.length < 2) {
            return null;
        }

        return round.map((set, index) => previewForEntry({ blockIndex: entry.blockIndex, block: entry.block, set }, String.fromCharCode(65 + index)));
    });

    const finishWorkout = async () => {
        if (props.workout.status !== 'in_progress') {
            return;
        }
        await finishWorkoutMutation(props.workout.id, {
            mutating,
            leaveConfirmed,
            confirm: async () => {
                const incomplete = flatSets.value.some(({ set }) => !set.completed);
                if (!incomplete) {
                    return true;
                }
                return confirmDialog({
                    title: 'Finish now?',
                    description: 'Incomplete sets stay incomplete.',
                    confirmLabel: 'Finish',
                });
            },
        });
    };

    const abandonWorkout = async () => {
        if (props.workout.status !== 'in_progress') {
            return;
        }
        await abandonWorkoutMutation(props.workout.id, {
            mutating,
            leaveConfirmed,
            confirm: async () =>
                confirmDialog({
                    title: 'Abandon this workout?',
                    description: 'It will not count as finished.',
                    confirmLabel: 'Abandon',
                    variant: 'destructive',
                }),
        });
    };

    const roundsInBlock = computed(() => (current.value ? workingRoundsInBlock(current.value.block) : 0));

    const canAddWorkingSet = computed(
        () => props.workout.status === 'in_progress' && current.value !== null && current.value.set.group_type === 'working',
    );

    const canRemoveWorkingSet = computed(() => {
        if (!current.value || props.workout.status !== 'in_progress') {
            return false;
        }
        if (current.value.set.group_type !== 'working' || current.value.set.completed) {
            return false;
        }
        if (roundsInBlock.value <= 1) {
            return false;
        }

        const index = current.value.set.set_index;
        const hasLaterRound = current.value.block.sets.some((s) => s.group_type === 'working' && s.set_index > index);
        if (!hasLaterRound) {
            // Last round: use Skip rest of block instead of − Set.
            return false;
        }

        const round = current.value.block.sets.filter((s) => s.group_type === 'working' && s.set_index === index);
        return round.every((s) => !s.completed);
    });

    const skipRestOfBlockTarget = computed(() => {
        if (props.workout.status !== 'in_progress') {
            return null;
        }

        if (current.value?.block.sets.some((s) => !s.completed)) {
            return current.value.block;
        }

        if (restSecondsLeft.value <= 0 && pendingRestSeconds.value <= 0) {
            return null;
        }

        const upcomingEntry = flatSets.value.find(({ set }) => !set.completed) ?? null;
        if (!upcomingEntry?.block.sets.some((s) => !s.completed)) {
            return null;
        }

        return upcomingEntry.block;
    });

    const canSkipRestOfBlock = computed(() => skipRestOfBlockTarget.value !== null);

    const addAdHocExercise = (exerciseId: number | null): void => {
        if (exerciseId === null || props.workout.status !== 'in_progress') {
            return;
        }

        addAdHocExerciseMutation(props.workout.id, exerciseId, { mutating });
    };

    const canRemoveAdHocBlock = computed(() => {
        const block = currentBlock.value;

        return props.workout.status === 'in_progress' && block !== null && block.is_ad_hoc && block.sets.every((set) => !set.completed);
    });

    const removeAdHocBlock = async (): Promise<void> => {
        const block = currentBlock.value;
        if (mutating.value || !block || !canRemoveAdHocBlock.value) {
            return;
        }

        const ok = await confirmDialog({
            title: 'Remove extra exercise?',
            description: 'This removes the exercise from this workout only.',
            confirmLabel: 'Remove',
            variant: 'destructive',
        });
        if (!ok || mutating.value || currentBlock.value?.id !== block.id) {
            return;
        }

        removeAdHocBlockMutation(props.workout.id, block.id, {
            mutating,
            onSuccess: () => {
                clearRest();
                pendingRestSeconds.value = 0;
                focus.value = firstIncomplete();
            },
        });
    };

    const addWorkingSet = () => {
        if (!current.value) {
            return;
        }
        addWorkingSetMutation(props.workout.id, current.value.block.id, { mutating });
    };

    const removeWorkingSet = () => {
        if (!current.value || !canRemoveWorkingSet.value) {
            return;
        }
        removeWorkingSetMutation(props.workout.id, current.value.set.id, { mutating });
    };

    const skipRestOfBlock = async () => {
        const block = skipRestOfBlockTarget.value;
        if (mutating.value || !block || !canSkipRestOfBlock.value) {
            return;
        }

        const ok = await confirmDialog({
            title: 'Skip rest of this group?',
            description: 'Remaining sets won’t appear in History.',
            confirmLabel: 'Skip',
        });
        if (!ok || mutating.value || skipRestOfBlockTarget.value?.id !== block.id) {
            return;
        }

        skipRestOfBlockMutation(props.workout.id, block.id, {
            mutating,
            onSuccess: () => {
                clearRest();
                focus.value = firstIncomplete();
            },
        });
    };

    const plateLoad = computed(() => {
        if (!current.value) {
            return null;
        }
        return logSheetOpen.value ? logPlateLoadDraft.value : plateLoadForEntry(current.value, setForm.weight_kg);
    });

    const stageWeightKg = computed(() => {
        if (!current.value) {
            return null;
        }
        const entry = current.value;
        if (entry.set.is_dropset) {
            return null;
        }
        if (stageWeightOverrideKg.value != null) {
            return stageWeightOverrideKg.value;
        }
        if (entry.set.group_type === 'warm_up') {
            return entry.set.logged_weight_kg ?? entry.set.target_weight_kg ?? null;
        }
        return workingWeightForEntry(entry);
    });

    const stageDropsetWeights = computed(() => {
        if (!current.value?.set.is_dropset) {
            return [] as number[];
        }
        const entry = current.value;
        if (entry.set.segments.length >= 2) {
            return entry.set.segments.map((segment) => segment.weight_kg);
        }
        return defaultPromoteSegments(workingWeightForSet(entry)).map((segment) => segment.weight_kg);
    });

    const stagePlateLoad = computed(() => {
        if (!current.value || current.value.set.is_dropset) {
            return null;
        }
        const weight = stageWeightKg.value;
        if (weight == null) {
            return null;
        }
        return plateLoadForEntry(current.value, weight);
    });

    const applyNearestLoad = () => {
        const entry = current.value;
        const load = plateLoad.value;
        if (!entry || !load) {
            return;
        }

        const normalized = normalizePlateLoadForOwnWeight(entry, load);
        logPlateLoadDraft.value = normalized;
        setForm.weight_kg = gramsToKg(normalized.total_g);
    };

    const applyStageNearestLoad = () => {
        if (!current.value || !stagePlateLoad.value) {
            return;
        }
        setStagePlateLoad(current.value, stagePlateLoad.value);
    };

    const changeStagePlate = (denominationG: number, change: 1 | -1) => {
        const entry = current.value;
        const load = stagePlateLoad.value;
        const weightKg = stageWeightKg.value;
        if (!entry || !load || weightKg == null || entry.set.is_dropset) {
            return;
        }

        const next = changePlateCount(weightKg, load, denominationG, change, props.plate_profile);
        if (!next) {
            return;
        }

        setStagePlateLoad(entry, next);
    };

    const formatPlateStack = computed(() => {
        const load = plateLoad.value;
        if (!load) {
            return null;
        }
        return formatPlateStackLabel(load, props.workout.weight_unit);
    });

    const stageFormatPlateStack = computed(() => {
        const load = stagePlateLoad.value;
        if (!load) {
            return null;
        }
        return formatPlateStackLabel(load, props.workout.weight_unit);
    });

    return {
        workout: computed(() => props.workout),
        plateProfile: computed(() => props.plate_profile),
        exerciseCatalog,
        muscleGroups,
        equipmentOptions,
        focus,
        current,
        currentBlock,
        currentExercise,
        logProgressionHints,
        setForm,
        draftSegments,
        restSecondsLeft,
        restLabel,
        pendingMidBlockBump,
        acceptMidBlockBump,
        declineMidBlockBump,
        logSheetOpen,
        mutating,
        progressLabel,
        upcoming,
        setupHint,
        setupSupersetPair,
        supersetNext,
        canPromoteToDropset,
        canDemoteFromDropset,
        canAddWorkingSet,
        canRemoveWorkingSet,
        canSkipRestOfBlock,
        canRemoveAdHocBlock,
        plateLoad,
        stagePlateLoad,
        stageWeightKg,
        stageDropsetWeights,
        formatPlateStack,
        stageFormatPlateStack,
        groupLabel,
        gramsToKg,
        openLogSheet,
        cancelLogSheet,
        completeSet,
        addDropSegment,
        removeDropSegment,
        promoteToDropset,
        demoteFromDropset,
        skipRest,
        acknowledgeSetup,
        finishWorkout,
        abandonWorkout,
        leaveWorkout,
        addWorkingSet,
        removeWorkingSet,
        addAdHocExercise,
        removeAdHocBlock,
        skipRestOfBlock,
        applyNearestLoad,
        applyStageNearestLoad,
        changeLogPlate,
        changeStagePlate,
        handleLogWeightInput,
    };
}

export function useWorkoutPlayer(): WorkoutPlayer {
    const player = inject(workoutPlayerKey);
    if (!player) {
        throw new Error('WorkoutPlayer not provided');
    }
    return player;
}
