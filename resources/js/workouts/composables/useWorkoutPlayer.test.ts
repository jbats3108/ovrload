import * as confirmDialog from '@/shared/lib/confirmDialog';
import * as haptics from '@/shared/lib/haptics';
import { plateProfile, playerBlock, playerSet, workoutPayload } from '@/test/factories';
import { inertiaMocks } from '@/test/inertiaMocks';
import { createWorkoutPlayer, useWorkoutPlayer, workoutPlayerKey } from '@/workouts/composables/useWorkoutPlayer';
import * as playerInteraction from '@/workouts/lib/playerInteraction';
import * as restAlert from '@/workouts/lib/restAlert';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';

function mountPlayer(overrides: Parameters<typeof workoutPayload>[0] = {}, plateProfileOverrides: Parameters<typeof plateProfile>[0] = {}) {
    let player!: ReturnType<typeof createWorkoutPlayer>;
    const Wrapper = defineComponent({
        setup() {
            player = createWorkoutPlayer({
                workout: workoutPayload(overrides),
                plate_profile: plateProfile(plateProfileOverrides),
            });
            return () => h('div');
        },
    });
    mount(Wrapper);
    return player;
}

describe('createWorkoutPlayer', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.spyOn(playerInteraction, 'preparePlayerInteraction').mockImplementation(() => {});
        vi.spyOn(restAlert, 'notifyRestEnded').mockImplementation(() => {});
        vi.spyOn(restAlert, 'notifyRestCountdown').mockImplementation(() => {});
        vi.spyOn(haptics, 'hapticTap').mockImplementation(() => {});
        vi.spyOn(haptics, 'hapticConfirm').mockImplementation(() => {});
        vi.spyOn(confirmDialog, 'confirmDialog').mockResolvedValue(true);
        vi.stubGlobal(
            'route',
            vi.fn((name: string, _params?: unknown) => `/${String(name)}`),
        );
        Object.defineProperty(navigator, 'wakeLock', {
            configurable: true,
            value: { request: vi.fn().mockRejectedValue(new Error('unsupported')) },
        });
    });

    afterEach(() => {
        vi.useRealTimers();
        sessionStorage.clear();
    });

    it('focuses first incomplete set', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ id: 7, completed: false })],
                }),
            ],
        });
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 7 });
    });

    it('builds upcoming preview for next set', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ id: 1, completed: true, logged_weight_kg: 90 }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        expect(player.upcoming.value?.exerciseName).toBe('Squat');
        expect(player.upcoming.value?.weightLabel).toBe('90');
        expect(player.upcoming.value?.setNumber).toBe(2);
        expect(player.upcoming.value?.setCount).toBe(2);
    });

    it('names the next exercise in a superset round with its target', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    is_superset: true,
                    exercises: [
                        {
                            id: 10,
                            name: 'Press',
                            working_weight_kg: 50,
                            prescribed_reps: 8,
                            achievement_floor: null,
                            progression_target: null,
                            position: 0,
                        },
                        {
                            id: 11,
                            name: 'Row',
                            working_weight_kg: 60,
                            prescribed_reps: 10,
                            achievement_floor: null,
                            progression_target: null,
                            position: 1,
                        },
                    ],
                    sets: [
                        playerSet({ id: 1, workout_block_exercise_id: 10, exercise_name: 'Press', set_index: 0 }),
                        playerSet({
                            id: 2,
                            workout_block_exercise_id: 11,
                            exercise_name: 'Row',
                            set_index: 0,
                            target_weight_kg: 60,
                            target_reps: 10,
                        }),
                    ],
                }),
            ],
        });
        expect(player.supersetNext.value).toEqual({
            exerciseName: 'Row',
            targetLabel: '60kg × 10',
            label: 'Then: Row (60kg × 10)',
        });
    });

    it('previews both superset exercises during setup', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    is_superset: true,
                    has_setup_after_warm_up: true,
                    exercises: [
                        {
                            id: 10,
                            name: 'Press',
                            working_weight_kg: 50,
                            prescribed_reps: 8,
                            achievement_floor: null,
                            progression_target: null,
                            position: 0,
                        },
                        {
                            id: 11,
                            name: 'Row',
                            working_weight_kg: 60,
                            prescribed_reps: 10,
                            achievement_floor: null,
                            progression_target: null,
                            position: 1,
                        },
                    ],
                    sets: [
                        playerSet({ id: 1, group_type: 'warm_up', workout_block_exercise_id: 10, exercise_name: 'Press', completed: true }),
                        playerSet({
                            id: 2,
                            group_type: 'working',
                            workout_block_exercise_id: 10,
                            exercise_name: 'Press',
                            set_index: 0,
                            target_weight_kg: 50,
                            target_reps: 8,
                        }),
                        playerSet({
                            id: 3,
                            group_type: 'working',
                            workout_block_exercise_id: 11,
                            exercise_name: 'Row',
                            set_index: 0,
                            target_weight_kg: 60,
                            target_reps: 10,
                        }),
                    ],
                }),
            ],
        });
        expect(player.focus.value.kind).toBe('setup');
        expect(player.setupSupersetPair.value?.map((item) => item.exerciseName)).toEqual(['Press', 'Row']);
        expect(player.setupSupersetPair.value?.map((item) => item.letter)).toEqual(['A', 'B']);
        expect(player.setupSupersetPair.value?.[0].weightLabel).toBe('50');
        expect(player.setupSupersetPair.value?.[1].weightLabel).toBe('60');
        expect(player.setupSteps.value.map((step) => step.exerciseName)).toEqual(['Press', 'Row']);
        expect(player.setupSteps.value.map((step) => step.letter)).toEqual(['A', 'B']);
        expect(player.setupSteps.value[0]?.plateLoad).not.toBeNull();
        expect(player.setupSteps.value[1]?.plateLoad).not.toBeNull();
        expect(player.setupSteps.value[0]?.formatPlateStack).toBeTruthy();
    });

    it('omits setup steps outside setup focus', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    is_superset: true,
                    exercises: [
                        {
                            id: 10,
                            name: 'Press',
                            working_weight_kg: 50,
                            prescribed_reps: 8,
                            achievement_floor: null,
                            progression_target: null,
                            position: 0,
                        },
                        {
                            id: 11,
                            name: 'Row',
                            working_weight_kg: 60,
                            prescribed_reps: 10,
                            achievement_floor: null,
                            progression_target: null,
                            position: 1,
                        },
                    ],
                    sets: [
                        playerSet({ id: 1, workout_block_exercise_id: 10, exercise_name: 'Press', set_index: 0 }),
                        playerSet({ id: 2, workout_block_exercise_id: 11, exercise_name: 'Row', set_index: 0 }),
                    ],
                }),
            ],
        });
        expect(player.focus.value.kind).toBe('set');
        expect(player.setupSupersetPair.value).toBeNull();
        expect(player.setupSteps.value).toEqual([]);
    });

    it('carries setup plate edits into the following set stage', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    has_setup_after_warm_up: true,
                    sets: [
                        playerSet({ id: 1, group_type: 'warm_up', completed: true, rest_seconds: 0 }),
                        playerSet({
                            id: 2,
                            group_type: 'working',
                            set_index: 0,
                            target_weight_kg: 80,
                            rest_seconds: 0,
                            completed: false,
                        }),
                    ],
                }),
            ],
        });
        expect(player.focus.value.kind).toBe('setup');
        expect(player.setupSteps.value).toHaveLength(1);
        expect(player.setupSteps.value[0]?.plateLoad).not.toBeNull();

        player.changeSetupPlate(2, 10_000, -1);
        expect(player.setupSteps.value[0]?.plateLoad?.per_side).toEqual([{ denomination_g: 20_000, count: 1, colour: null }]);
        expect(player.setupSteps.value[0]?.plateLoad?.total_g).toBe(60_000);

        player.acknowledgeSetup();
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 2 });
        expect(player.stagePlateLoad.value?.total_g).toBe(60_000);
        expect(player.stageWeightKg.value).toBe(60);
        expect(player.stagePlateLoad.value?.per_side).toEqual([{ denomination_g: 20_000, count: 1, colour: null }]);
    });

    it('syncs draft weight from previous logged set', async () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 95 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
            ],
        });
        await vi.waitFor(() => {
            expect(player.setForm.weight_kg).toBe(95);
        });
    });

    it('reports progress label', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ completed: true }), playerSet({ id: 2, completed: false })],
                }),
            ],
        });
        expect(player.progressLabel.value).toBe('1/2');
    });

    it('posts set completion payload', () => {
        const player = mountPlayer();
        player.setForm.reps = 5;
        player.setForm.weight_kg = 100;
        player.logSheetOpen.value = true;
        player.completeSet();
        expect(inertiaMocks().inertiaFormPost).toHaveBeenCalledWith(
            '/workouts.sets.complete',
            expect.objectContaining({ preserveScroll: true, only: ['workout'] }),
        );
    });

    it('manages dropset draft segments', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({
                            is_dropset: true,
                            segments: [
                                { position: 0, weight_kg: 80 },
                                { position: 1, weight_kg: 70 },
                            ],
                        }),
                    ],
                }),
            ],
        });
        expect(player.draftSegments.value).toEqual([{ weight_kg: 80 }, { weight_kg: 70 }]);
        player.addDropSegment();
        expect(player.draftSegments.value).toHaveLength(3);
        player.removeDropSegment(2);
        expect(player.draftSegments.value).toHaveLength(2);
    });

    it('skips rest and advances focus', async () => {
        vi.useFakeTimers();
        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            options?.onSuccess?.();
        });
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ id: 1, completed: false, rest_seconds: 90 }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();
        expect(player.restSecondsLeft.value).toBe(90);
        expect(playerInteraction.preparePlayerInteraction).toHaveBeenCalled();
        player.skipRest();
        expect(player.restSecondsLeft.value).toBe(0);
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });
    });

    it('restores acknowledged setup after remount (resume)', () => {
        const blocks = [
            playerBlock({
                has_setup_after_warm_up: true,
                sets: [
                    playerSet({ id: 1, group_type: 'warm_up', completed: true }),
                    playerSet({ id: 2, group_type: 'working', completed: false, rest_seconds: 0 }),
                ],
            }),
        ];
        const first = mountPlayer({ blocks });
        expect(first.focus.value.kind).toBe('setup');
        first.acknowledgeSetup();
        expect(first.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 2 });

        const resumed = mountPlayer({ blocks });
        expect(resumed.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 2 });
    });

    it('restores an in-progress rest timer after remount (resume)', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-09-04T12:00:00.000Z'));
        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            options?.onSuccess?.();
        });
        const blocks = [
            playerBlock({
                sets: [playerSet({ id: 1, completed: false, rest_seconds: 90 }), playerSet({ id: 2, set_index: 1, completed: false })],
            }),
        ];
        const first = mountPlayer({ blocks });
        first.logSheetOpen.value = true;
        first.completeSet();
        await flushPromises();
        expect(first.restSecondsLeft.value).toBe(90);

        vi.setSystemTime(new Date('2026-09-04T12:00:30.000Z'));
        const resumed = mountPlayer({ blocks });
        expect(resumed.restSecondsLeft.value).toBe(60);
    });

    it('alerts when rest timer completes', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T12:00:00Z'));
        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            options?.onSuccess?.();
        });
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ id: 1, completed: false, rest_seconds: 3 }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();
        vi.advanceTimersByTime(3000);
        expect(restAlert.notifyRestEnded).toHaveBeenCalled();
        expect(player.restSecondsLeft.value).toBe(0);
    });

    it('beeps once per remaining second in the last five seconds of rest', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-01-01T12:00:00Z'));
        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            options?.onSuccess?.();
        });
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ id: 1, completed: false, rest_seconds: 7 }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();

        expect(restAlert.notifyRestCountdown).not.toHaveBeenCalled();

        vi.advanceTimersByTime(2000);
        expect(restAlert.notifyRestCountdown).toHaveBeenCalledWith(5);

        vi.advanceTimersByTime(1000);
        expect(restAlert.notifyRestCountdown).toHaveBeenCalledWith(4);

        vi.advanceTimersByTime(3000);
        expect(restAlert.notifyRestCountdown).toHaveBeenCalledWith(1);
        expect(restAlert.notifyRestCountdown).toHaveBeenCalledTimes(5);
    });

    it('acknowledges setup after warm-up', () => {
        vi.useFakeTimers();
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    has_setup_after_warm_up: true,
                    sets: [
                        playerSet({ id: 1, group_type: 'warm_up', completed: true }),
                        playerSet({ id: 2, group_type: 'working', completed: false, rest_seconds: 60 }),
                    ],
                }),
            ],
        });
        expect(player.focus.value.kind).toBe('setup');
        player.acknowledgeSetup();
        expect(player.restSecondsLeft.value).toBe(60);
    });

    it('acknowledges setup between warm-up steps', () => {
        vi.useFakeTimers();
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({ id: 1, group_type: 'warm_up', set_index: 0, completed: true, has_setup_after: true, rest_seconds: 45 }),
                        playerSet({ id: 2, group_type: 'warm_up', set_index: 1, completed: false }),
                        playerSet({ id: 3, group_type: 'working', completed: false }),
                    ],
                }),
            ],
        });
        expect(player.focus.value).toEqual({
            kind: 'setup',
            blockIndex: 0,
            phase: 'after_warm_up_step',
            warmUpStepIndex: 0,
        });
        player.acknowledgeSetup();
        expect(player.restSecondsLeft.value).toBe(45);
    });

    it('applies nearest plate load to draft weight', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 97.5 })],
                }),
            ],
        });
        player.setForm.weight_kg = 97.5;
        player.applyNearestLoad();
        expect(player.setForm.weight_kg).toBe(95);
    });

    it('applies nearest plate load from the main stage target', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 97.5 })],
                }),
            ],
        });
        expect(player.stageWeightKg.value).toBe(97.5);
        expect(player.stagePlateLoad.value?.exact).toBe(false);

        player.applyStageNearestLoad();

        expect(player.stageWeightKg.value).toBe(95);
        expect(player.setForm.weight_kg).toBe(95);
        expect(player.stagePlateLoad.value?.exact).toBe(true);
    });

    it('keeps stage nearest weight when opening the log sheet', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 97.5 })],
                }),
            ],
        });
        player.applyStageNearestLoad();
        player.openLogSheet();

        expect(player.setForm.weight_kg).toBe(95);
        expect(player.stageWeightKg.value).toBe(95);
    });

    it('seeds logging from the calculated barbell load', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 97.5 })],
                }),
            ],
        });

        player.openLogSheet();

        expect(player.setForm.weight_kg).toBe(95);
        expect(player.plateLoad.value?.total_g).toBe(95_000);
        expect(player.plateLoad.value?.exact).toBe(true);
    });

    it('persists the snapped nearest stack so the next set can reuse it', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({ id: 1, equipment: 'barbell', target_weight_kg: 97.5 }),
                        playerSet({ id: 2, set_index: 1, equipment: 'barbell', target_weight_kg: 97.5 }),
                    ],
                }),
            ],
        });

        player.openLogSheet();
        player.completeSet();

        expect(inertiaMocks().lastTransformed).toEqual(
            expect.objectContaining({
                weight_kg: 95,
                plate_stack: {
                    bar_g: 20_000,
                    per_side: [
                        { denomination_g: 20_000, count: 1 },
                        { denomination_g: 10_000, count: 1 },
                        { denomination_g: 5_000, count: 1 },
                        { denomination_g: 2_500, count: 1 },
                    ],
                },
            }),
        );
    });

    it('edits the stage stack and carries its calculated weight into logging', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 80 })],
                }),
            ],
        });

        player.changeStagePlate(10_000, -1);

        expect(player.stageWeightKg.value).toBe(60);
        expect(player.stagePlateLoad.value?.per_side).toEqual([{ denomination_g: 20_000, count: 1, colour: null }]);

        player.openLogSheet();
        expect(player.setForm.weight_kg).toBe(60);
        expect(player.plateLoad.value?.per_side).toEqual([{ denomination_g: 20_000, count: 1, colour: null }]);
    });

    it('uses a persisted previous stack for the next working set', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({
                            id: 1,
                            completed: true,
                            logged_weight_kg: 80,
                            plate_stack: {
                                bar_g: 20_000,
                                per_side: [
                                    { denomination_g: 20_000, count: 1 },
                                    { denomination_g: 10_000, count: 1 },
                                ],
                            },
                        }),
                        playerSet({ id: 2, set_index: 1, target_weight_kg: 80 }),
                    ],
                }),
            ],
        });

        expect(player.stagePlateLoad.value?.per_side).toEqual([
            { denomination_g: 20_000, count: 1, colour: null },
            { denomination_g: 10_000, count: 1, colour: null },
        ]);
    });

    it('builds the next warm-up stack from the previous warm-up plates', () => {
        const richPlates = {
            plates: [
                { denomination_g: 25_000, count: 2, colour: null },
                { denomination_g: 20_000, count: 2, colour: null },
                { denomination_g: 10_000, count: 4, colour: null },
                { denomination_g: 5_000, count: 4, colour: null },
                { denomination_g: 2_500, count: 4, colour: null },
                { denomination_g: 1_250, count: 4, colour: null },
            ],
        };
        const player = mountPlayer(
            {
                blocks: [
                    playerBlock({
                        sets: [
                            playerSet({
                                id: 1,
                                group_type: 'warm_up',
                                set_index: 2,
                                completed: true,
                                logged_weight_kg: 60,
                                target_weight_kg: 60,
                                plate_stack: {
                                    bar_g: 20_000,
                                    per_side: [{ denomination_g: 20_000, count: 1 }],
                                },
                            }),
                            playerSet({
                                id: 2,
                                group_type: 'warm_up',
                                set_index: 3,
                                target_weight_kg: 72.5,
                            }),
                        ],
                    }),
                ],
            },
            richPlates,
        );

        expect(player.stagePlateLoad.value?.per_side).toEqual([
            { denomination_g: 20_000, count: 1, colour: null },
            { denomination_g: 5_000, count: 1, colour: null },
            { denomination_g: 1_250, count: 1, colour: null },
        ]);
        expect(player.stagePlateLoad.value?.total_g).toBe(72_500);
    });

    it('submits the final edited stack with the logged weight', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [playerSet({ equipment: 'barbell', target_weight_kg: 80 })],
                }),
            ],
        });

        player.openLogSheet();
        player.changeLogPlate(10_000, -1);
        player.completeSet();

        expect(inertiaMocks().lastTransformed).toEqual(
            expect.objectContaining({
                weight_kg: 60,
                plate_stack: {
                    bar_g: 20_000,
                    per_side: [{ denomination_g: 20_000, count: 1 }],
                },
            }),
        );
    });

    it('exposes canPromoteToDropset for working sets', () => {
        const player = mountPlayer();
        expect(player.canPromoteToDropset.value).toBe(true);
        expect(player.canDemoteFromDropset.value).toBe(false);
    });

    it('promotes working set to dropset', () => {
        const player = mountPlayer();
        player.promoteToDropset();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.sets.promote-dropset',
            expect.objectContaining({ segments: expect.any(Array) }),
            expect.objectContaining({ preserveScroll: true, only: ['workout'] }),
        );
    });

    it('exposes canDemoteFromDropset for incomplete dropsets', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({
                            is_dropset: true,
                            segments: [
                                { position: 1, weight_kg: 100 },
                                { position: 2, weight_kg: 80 },
                            ],
                        }),
                    ],
                }),
            ],
        });

        expect(player.canDemoteFromDropset.value).toBe(true);
        expect(player.canPromoteToDropset.value).toBe(false);
    });

    it('demotes dropset to single', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    sets: [
                        playerSet({
                            is_dropset: true,
                            segments: [
                                { position: 1, weight_kg: 100 },
                                { position: 2, weight_kg: 80 },
                            ],
                        }),
                    ],
                }),
            ],
        });

        player.demoteFromDropset();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.sets.demote-dropset',
            {},
            expect.objectContaining({ preserveScroll: true, only: ['workout'] }),
        );
    });

    it('finishes workout when confirmed', async () => {
        const player = mountPlayer();
        await player.finishWorkout();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith('/workouts.finish', {}, expect.any(Object));
    });

    it('abandons workout when confirmed', async () => {
        const player = mountPlayer();
        await player.abandonWorkout();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith('/workouts.discard', {}, expect.any(Object));
    });

    it('leaves workout via dashboard visit', async () => {
        const player = mountPlayer();
        await player.leaveWorkout();
        expect(inertiaMocks().routerMocks.visit).toHaveBeenCalledWith('/dashboard');
    });

    it('cancels leave when confirm is declined', async () => {
        vi.mocked(confirmDialog.confirmDialog).mockResolvedValueOnce(false);
        const player = mountPlayer();
        await player.leaveWorkout();
        expect(inertiaMocks().routerMocks.visit).not.toHaveBeenCalled();
    });

    it('adds and removes working sets', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [playerSet({ id: 1, set_index: 0, completed: false }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        expect(player.canAddWorkingSet.value).toBe(true);
        expect(player.canRemoveWorkingSet.value).toBe(true);
        player.addWorkingSet();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.working-sets.add',
            {},
            expect.objectContaining({ preserveScroll: true, only: ['workout'], onFinish: expect.any(Function) }),
        );
        player.mutating.value = false;
        player.removeWorkingSet();
        expect(inertiaMocks().routerMocks.delete).toHaveBeenCalledWith(
            '/workouts.sets.remove',
            expect.objectContaining({ preserveScroll: true, only: ['workout'], onFinish: expect.any(Function) }),
        );
    });

    it('adds an ad-hoc exercise to the workout snapshot', () => {
        const player = mountPlayer();

        player.addAdHocExercise(42);

        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.ad-hoc-exercises.store',
            { exercise_id: 42 },
            expect.objectContaining({
                preserveScroll: true,
                only: ['workout'],
                onFinish: expect.any(Function),
            }),
        );
    });

    it('keeps the current focus when an ad-hoc block is added', async () => {
        const props = reactive({
            workout: workoutPayload({
                blocks: [
                    playerBlock({
                        id: 5,
                        sets: [playerSet({ id: 1, completed: false })],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;

        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );

        player.addAdHocExercise(42);
        props.workout = workoutPayload({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [playerSet({ id: 1, completed: false })],
                }),
                playerBlock({
                    id: 6,
                    is_ad_hoc: true,
                    sets: [playerSet({ id: 4, completed: false })],
                }),
            ],
        });
        await nextTick();

        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });
    });

    it('allows removing an empty ad-hoc exercise block', async () => {
        const player = mountPlayer({
            blocks: [playerBlock({ is_ad_hoc: true })],
        });

        expect(player.canRemoveAdHocBlock.value).toBe(true);

        await player.removeAdHocBlock();

        expect(confirmDialog.confirmDialog).toHaveBeenCalledWith({
            title: 'Remove extra exercise?',
            description: 'This removes the exercise from this workout only.',
            confirmLabel: 'Remove',
            variant: 'destructive',
        });
        expect(inertiaMocks().routerMocks.delete).toHaveBeenCalledWith(
            '/workouts.ad-hoc-blocks.destroy',
            expect.objectContaining({
                preserveScroll: true,
                only: ['workout'],
                onSuccess: expect.any(Function),
                onFinish: expect.any(Function),
            }),
        );
    });

    it('does not offer ad-hoc removal after a set is logged', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    is_ad_hoc: true,
                    sets: [playerSet({ completed: true, logged_weight_kg: 80, logged_reps: 6 })],
                }),
            ],
        });

        expect(player.canRemoveAdHocBlock.value).toBe(false);
    });

    it('hides progression hints for ad-hoc exercises', () => {
        const player = mountPlayer({
            blocks: [playerBlock({ is_ad_hoc: true })],
        });

        expect(player.logProgressionHints.value).toBeNull();
    });

    it('ignores overlapping structure mutations while busy', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [playerSet({ id: 1, set_index: 0, completed: false }), playerSet({ id: 2, set_index: 1, completed: false })],
                }),
            ],
        });
        player.addWorkingSet();
        player.addWorkingSet();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledTimes(1);
    });

    it('hides remove on the last working set so it cannot skip the block', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
            ],
        });
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 2 });
        expect(player.canRemoveWorkingSet.value).toBe(false);
        player.removeWorkingSet();
        expect(inertiaMocks().routerMocks.delete).not.toHaveBeenCalled();
    });

    it('offers skip rest of block when incompletes remain', async () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
            ],
        });
        expect(player.canSkipRestOfBlock.value).toBe(true);
        await player.skipRestOfBlock();
        expect(confirmDialog.confirmDialog).toHaveBeenCalledWith({
            title: 'Skip rest of this group?',
            description: 'Remaining sets won’t appear in History.',
            confirmLabel: 'Skip',
        });
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.blocks.skip-rest',
            {},
            expect.objectContaining({
                preserveScroll: true,
                only: ['workout'],
                onSuccess: expect.any(Function),
                onFinish: expect.any(Function),
            }),
        );
    });

    it('does not skip rest of block when confirm is declined', async () => {
        vi.mocked(confirmDialog.confirmDialog).mockResolvedValueOnce(false);
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
            ],
        });
        await player.skipRestOfBlock();
        expect(inertiaMocks().routerMocks.post).not.toHaveBeenCalled();
    });

    it('offers skip rest of block during rest before the next set in the block', async () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
            ],
        });
        player.focus.value = { kind: 'set', blockIndex: 0, setId: 1 };
        player.restSecondsLeft.value = 90;
        expect(player.canSkipRestOfBlock.value).toBe(true);
        await player.skipRestOfBlock();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.blocks.skip-rest',
            {},
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
    });

    it('hides skip rest of block when the block has no incompletes', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 })],
                }),
                playerBlock({
                    id: 6,
                    sets: [playerSet({ id: 3, set_index: 0, completed: false })],
                }),
            ],
        });
        player.focus.value = { kind: 'set', blockIndex: 0, setId: 1 };
        expect(player.canSkipRestOfBlock.value).toBe(false);
    });

    it('parks an untouched group for later', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    position: 1,
                    sets: [playerSet({ id: 1, set_index: 0, completed: false })],
                }),
                playerBlock({
                    id: 6,
                    position: 2,
                    sets: [playerSet({ id: 2, set_index: 0, completed: false })],
                }),
            ],
        });
        expect(player.canParkForLater.value).toBe(true);
        player.parkForLater();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.blocks.later',
            {},
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
    });

    it('offers Later on the second group when a third remains', async () => {
        const props = reactive({
            workout: workoutPayload({
                blocks: [
                    playerBlock({
                        id: 5,
                        position: 1,
                        sets: [playerSet({ id: 1, set_index: 0, completed: false })],
                    }),
                    playerBlock({
                        id: 6,
                        position: 2,
                        sets: [playerSet({ id: 2, set_index: 0, completed: false })],
                    }),
                    playerBlock({
                        id: 7,
                        position: 3,
                        sets: [playerSet({ id: 3, set_index: 0, completed: false })],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );

        inertiaMocks().routerMocks.post.mockImplementation(
            (_url: string, _data: unknown, options?: { onSuccess?: () => void; onFinish?: () => void }) => {
                props.workout = workoutPayload({
                    blocks: [
                        playerBlock({
                            id: 5,
                            position: 1,
                            is_parked: true,
                            sets: [playerSet({ id: 1, set_index: 0, completed: false })],
                        }),
                        playerBlock({
                            id: 6,
                            position: 2,
                            sets: [playerSet({ id: 2, set_index: 0, completed: false })],
                        }),
                        playerBlock({
                            id: 7,
                            position: 3,
                            sets: [playerSet({ id: 3, set_index: 0, completed: false })],
                        }),
                    ],
                });
                options?.onSuccess?.();
                options?.onFinish?.();
            },
        );

        expect(player.canParkForLater.value).toBe(true);
        player.parkForLater();
        await flushPromises();
        await nextTick();

        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 1, setId: 2 });
        expect(player.canParkForLater.value).toBe(true);
    });

    it('hides Later when the focused group already has a logged set', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    position: 1,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100 }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                    ],
                }),
                playerBlock({
                    id: 6,
                    position: 2,
                    sets: [playerSet({ id: 3, set_index: 0, completed: false })],
                }),
            ],
        });
        expect(player.canParkForLater.value).toBe(false);
        expect(player.canSkipRestOfBlock.value).toBe(true);
    });

    it('confirms Skip group when the target block is untouched', async () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    id: 5,
                    position: 1,
                    sets: [playerSet({ id: 1, set_index: 0, completed: false })],
                }),
                playerBlock({
                    id: 6,
                    position: 2,
                    sets: [playerSet({ id: 2, set_index: 0, completed: false })],
                }),
            ],
        });
        await player.skipRestOfBlock();
        expect(confirmDialog.confirmDialog).toHaveBeenCalledWith({
            title: 'Skip this group?',
            description: 'Remaining sets won’t appear in History.',
            confirmLabel: 'Skip',
        });
    });

    it('offers parked groups when only parked work remains', async () => {
        inertiaMocks().routerMocks.post.mockImplementation(
            (_url: string, _data: unknown, options?: { onSuccess?: () => void; onFinish?: () => void }) => {
                options?.onSuccess?.();
                options?.onFinish?.();
            },
        );
        const props = reactive({
            workout: workoutPayload({
                blocks: [
                    playerBlock({
                        id: 5,
                        position: 1,
                        is_parked: true,
                        sets: [playerSet({ id: 1, set_index: 0, completed: false })],
                    }),
                    playerBlock({
                        id: 6,
                        position: 2,
                        sets: [playerSet({ id: 2, set_index: 0, completed: true, logged_weight_kg: 100 })],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );
        expect(player.focus.value).toEqual({ kind: 'done' });
        expect(player.awaitingParkedOffer.value).toBe(true);
        await flushPromises();
        expect(confirmDialog.confirmDialog).toHaveBeenCalledWith({
            title: 'You left 1 group for later — do them now?',
            confirmLabel: 'Yes',
            cancelLabel: 'No thanks',
        });
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledWith(
            '/workouts.clear-parked',
            {},
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
    });

    it('keeps focus on the current set when an extra set is added to the workout payload', async () => {
        const props = reactive({
            workout: workoutPayload({
                blocks: [
                    playerBlock({
                        id: 5,
                        sets: [playerSet({ id: 1, set_index: 0, completed: false }), playerSet({ id: 2, set_index: 1, completed: false })],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });

        props.workout = workoutPayload({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [
                        playerSet({ id: 1, set_index: 0, completed: false }),
                        playerSet({ id: 2, set_index: 1, completed: false }),
                        playerSet({ id: 3, set_index: 2, completed: false }),
                    ],
                }),
            ],
        });
        await nextTick();
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });
    });

    it('refocuses when the focused set is removed from the workout payload', async () => {
        const props = reactive({
            workout: workoutPayload({
                blocks: [
                    playerBlock({
                        id: 5,
                        sets: [playerSet({ id: 1, set_index: 0, completed: false }), playerSet({ id: 2, set_index: 1, completed: false })],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 1 });

        props.workout = workoutPayload({
            blocks: [
                playerBlock({
                    id: 5,
                    sets: [playerSet({ id: 2, set_index: 0, completed: false })],
                }),
            ],
        });
        await nextTick();
        expect(player.focus.value).toEqual({ kind: 'set', blockIndex: 0, setId: 2 });
    });

    it('opens and cancels the log sheet without posting', () => {
        const player = mountPlayer();
        player.openLogSheet();
        expect(player.logSheetOpen.value).toBe(true);
        expect(haptics.hapticTap).toHaveBeenCalled();
        player.cancelLogSheet();
        expect(player.logSheetOpen.value).toBe(false);
        expect(inertiaMocks().inertiaFormPost).not.toHaveBeenCalled();
    });

    it('exposes floor and bump hints for working sets on the log sheet', () => {
        const player = mountPlayer({
            blocks: [
                playerBlock({
                    exercises: [
                        {
                            id: 10,
                            name: 'Squat',
                            working_weight_kg: 100,
                            prescribed_reps: 5,
                            achievement_floor: 4,
                            progression_target: 6,
                            position: 0,
                        },
                    ],
                    sets: [playerSet({ id: 1, workout_block_exercise_id: 10, group_type: 'working' })],
                }),
            ],
        });

        expect(player.logProgressionHints.value).toBe('Floor 4. Bump @ 5');
    });

    it('omits bump hint on deload working sets', () => {
        const withFloor = mountPlayer({
            mode: 'deload',
            blocks: [
                playerBlock({
                    exercises: [
                        {
                            id: 10,
                            name: 'Squat',
                            working_weight_kg: 50,
                            prescribed_reps: 3,
                            achievement_floor: 4,
                            progression_target: 5,
                            position: 0,
                        },
                    ],
                    sets: [playerSet({ id: 1, workout_block_exercise_id: 10, group_type: 'working' })],
                }),
            ],
        });
        expect(withFloor.logProgressionHints.value).toBe('Floor 4.');

        const withoutFloor = mountPlayer({
            mode: 'deload',
            blocks: [
                playerBlock({
                    exercises: [
                        {
                            id: 10,
                            name: 'Squat',
                            working_weight_kg: 50,
                            prescribed_reps: 3,
                            achievement_floor: null,
                            progression_target: 5,
                            position: 0,
                        },
                    ],
                    sets: [playerSet({ id: 1, workout_block_exercise_id: 10, group_type: 'working' })],
                }),
            ],
        });
        expect(withoutFloor.logProgressionHints.value).toBeNull();
    });

    it('hides progression hints on warm-up and dropset log sheets', () => {
        const warmUp = mountPlayer({
            blocks: [
                playerBlock({
                    exercises: [
                        {
                            id: 10,
                            name: 'Squat',
                            working_weight_kg: 100,
                            prescribed_reps: 5,
                            achievement_floor: 4,
                            progression_target: 6,
                            position: 0,
                        },
                    ],
                    sets: [playerSet({ id: 1, workout_block_exercise_id: 10, group_type: 'warm_up' })],
                }),
            ],
        });
        expect(warmUp.logProgressionHints.value).toBeNull();

        const dropset = mountPlayer({
            blocks: [
                playerBlock({
                    exercises: [
                        {
                            id: 10,
                            name: 'Squat',
                            working_weight_kg: 100,
                            prescribed_reps: 5,
                            achievement_floor: 4,
                            progression_target: 6,
                            position: 0,
                        },
                    ],
                    sets: [
                        playerSet({
                            id: 1,
                            workout_block_exercise_id: 10,
                            group_type: 'working',
                            is_dropset: true,
                            segments: [
                                { position: 1, weight_kg: 100 },
                                { position: 2, weight_kg: 80 },
                            ],
                        }),
                    ],
                }),
            ],
        });
        expect(dropset.logProgressionHints.value).toBeNull();
    });

    it('auto-bumps the next working set weight after target is hit in progressive mode', async () => {
        const props = reactive({
            workout: workoutPayload({
                progression_style: 'progressive_overload',
                progressive_mid_block: 'auto',
                blocks: [
                    playerBlock({
                        sets: [
                            playerSet({ id: 1, equipment: null, completed: false, rest_seconds: 0, target_reps: 5, target_weight_kg: 100 }),
                            playerSet({ id: 2, equipment: null, set_index: 1, completed: false, target_reps: 5, target_weight_kg: 100 }),
                        ],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );

        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            const set = props.workout.blocks[0].sets[0];
            set.completed = true;
            set.logged_weight_kg = 100;
            set.logged_reps = 5;
            options?.onSuccess?.();
        });

        player.setForm.reps = 5;
        player.setForm.weight_kg = 100;
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();
        expect(player.upcoming.value?.weightLabel).toBe('102.5');
    });

    it('follows logged weight when auto bump prefill is ignored', async () => {
        const props = reactive({
            workout: workoutPayload({
                progression_style: 'progressive_overload',
                progressive_mid_block: 'auto',
                blocks: [
                    playerBlock({
                        sets: [
                            playerSet({ id: 1, equipment: null, completed: false, rest_seconds: 0, target_reps: 5, target_weight_kg: 100 }),
                            playerSet({
                                id: 2,
                                equipment: null,
                                set_index: 1,
                                completed: false,
                                rest_seconds: 0,
                                target_reps: 5,
                                target_weight_kg: 100,
                            }),
                            playerSet({ id: 3, equipment: null, set_index: 2, completed: false, target_reps: 5, target_weight_kg: 100 }),
                        ],
                    }),
                ],
            }),
            plate_profile: plateProfile(),
        });
        let player!: ReturnType<typeof createWorkoutPlayer>;
        mount(
            defineComponent({
                setup() {
                    player = createWorkoutPlayer(props);
                    return () => h('div');
                },
            }),
        );

        inertiaMocks().inertiaFormPost.mockImplementation((_url, options) => {
            const set = props.workout.blocks[0].sets.find((row) => !row.completed) ?? props.workout.blocks[0].sets[0];
            set.completed = true;
            set.logged_weight_kg = player.setForm.weight_kg;
            set.logged_reps = player.setForm.reps;
            options?.onSuccess?.();
        });

        player.setForm.reps = 5;
        player.setForm.weight_kg = 100;
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();
        expect(player.upcoming.value?.weightLabel).toBe('102.5');

        player.setForm.reps = 5;
        player.setForm.weight_kg = 100;
        player.logSheetOpen.value = true;
        player.completeSet();
        await flushPromises();
        expect(player.upcoming.value?.weightLabel).toBe('102.5');
    });

    it('confirms log set with a haptic', () => {
        const player = mountPlayer();
        player.openLogSheet();
        player.completeSet();
        expect(haptics.hapticConfirm).toHaveBeenCalled();
        expect(inertiaMocks().inertiaFormPost).toHaveBeenCalled();
    });
    it('ignores completeSet when the log sheet is closed', () => {
        const player = mountPlayer();
        player.completeSet();
        expect(inertiaMocks().inertiaFormPost).not.toHaveBeenCalled();
    });

    it('ignores completeSet when workout is not in progress', () => {
        const player = mountPlayer({ status: 'finished' });
        player.logSheetOpen.value = true;
        player.completeSet();
        expect(inertiaMocks().inertiaFormPost).not.toHaveBeenCalled();
    });
});

describe('useWorkoutPlayer', () => {
    it('throws when provider is missing', () => {
        const Wrapper = defineComponent({
            setup() {
                expect(() => useWorkoutPlayer()).toThrow('WorkoutPlayer not provided');
                return () => h('div');
            },
        });
        mount(Wrapper);
    });

    it('returns injected player', () => {
        const player = mountPlayer();
        let injected!: ReturnType<typeof createWorkoutPlayer>;
        const Wrapper = defineComponent({
            setup() {
                injected = useWorkoutPlayer();
                return () => h('div');
            },
        });
        mount(Wrapper, {
            global: { provide: { [workoutPlayerKey as symbol]: player } },
        });
        expect(injected).toBe(player);
    });
});
