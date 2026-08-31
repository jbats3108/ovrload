import { createRoutineEditor } from '@/routines/composables/useRoutineEditor';
import type { ExerciseProfileOption } from '@/settings/types';
import * as confirmDialog from '@/shared/lib/confirmDialog';
import { exerciseOption, routinePayload } from '@/test/factories';
import { inertiaMocks } from '@/test/inertiaMocks';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

const strengthProfile: ExerciseProfileOption = {
    id: 1,
    slug: 'preset-strength',
    name: 'Strength',
    display_name: 'OVRLOAD Strength',
    kind: 'preset',
    status: 'published',
    target_reps: 6,
    floor: 4,
    floor_override: null,
    working_rest_seconds: 180,
    warm_up_steps: [
        { percent: 50, reps: 5 },
        { percent: 75, reps: 3 },
        { percent: 90, reps: 1 },
    ],
    recipe_fingerprint: 'recipe-strength',
    exercise_fingerprint: 'exercise-strength',
    shared_fingerprint: 'shared-strength',
    reference_count: 0,
    stale_assignment_count: 0,
    is_default: true,
    assigned_routines: [],
};

const hypertrophyProfile: ExerciseProfileOption = {
    ...strengthProfile,
    id: 2,
    slug: 'preset-hypertrophy',
    name: 'Hypertrophy',
    display_name: 'OVRLOAD Hypertrophy',
    target_reps: 10,
    floor: 8,
    working_rest_seconds: 90,
    warm_up_steps: [
        { percent: 50, reps: 10 },
        { percent: 80, reps: 5 },
    ],
    recipe_fingerprint: 'recipe-hypertrophy',
    exercise_fingerprint: 'exercise-hypertrophy',
    shared_fingerprint: 'shared-hypertrophy',
};

function mountEditor(props = {}) {
    let editor!: ReturnType<typeof createRoutineEditor>;
    const Wrapper = defineComponent({
        setup() {
            editor = createRoutineEditor({
                routine: routinePayload({ blocks: [] }),
                exercises: [exerciseOption(), exerciseOption({ id: 2, name: 'Row' })],
                weight_unit: 'kg',
                warm_up_defaults: [{ percent: 40, reps: 5 }],
                warm_up_defaults_scope: 'all_blocks',
                ...props,
            });
            return () => h('div');
        },
    });
    mount(Wrapper);
    return editor;
}

describe('createRoutineEditor', () => {
    beforeEach(() => {
        inertiaMocks().inertiaFormPut.mockClear();
        inertiaMocks().routerMocks.post.mockClear();
        inertiaMocks().routerMocks.delete.mockClear();
        vi.spyOn(confirmDialog, 'confirmDialog').mockResolvedValue(true);
    });

    it('adds blocks and selects the new block', () => {
        const editor = mountEditor();
        editor.addBlock(false);
        expect(editor.form.blocks).toHaveLength(1);
        expect(editor.active.value).toBe(0);
        expect(editor.form.blocks[0].warm_up.steps).toHaveLength(1);
        expect(editor.form.blocks[0].exercises[0].prescribed_reps).toBe(6);
    });

    it('seeds new blocks with training default target reps', () => {
        const editor = mountEditor({ progression_target_default: 10 });
        editor.addBlock(false);
        expect(editor.form.blocks[0].exercises[0].prescribed_reps).toBe(10);
    });

    it('seeds new blocks from the routine profile', () => {
        const editor = mountEditor({
            routine: routinePayload({ blocks: [], default_exercise_profile_id: 1 }),
            exercise_profiles: [strengthProfile],
        });

        editor.addBlock(false);

        expect(editor.form.blocks[0].exercises[0]).toMatchObject({
            prescribed_reps: 6,
            achievement_floor: null,
            floor_is_derived: true,
            exercise_profile_id: 1,
            exercise_profile_fingerprint: 'recipe-strength',
        });
        expect(editor.form.blocks[0].working.rest_seconds).toBe(180);
        expect(editor.form.blocks[0].warm_up.steps).toEqual([
            { mode: 'percent', percent: 50, reps: 5, has_setup_after: false },
            { mode: 'percent', percent: 75, reps: 3, has_setup_after: false },
            { mode: 'percent', percent: 90, reps: 1, has_setup_after: false },
        ]);
    });

    it('copies the first exercise profile when turning a block into a superset', () => {
        const editor = mountEditor({
            routine: routinePayload({ blocks: [], default_exercise_profile_id: 1 }),
            exercise_profiles: [strengthProfile],
        });
        editor.addBlock(false);

        editor.toggleSuperset(editor.form.blocks[0]);

        expect(editor.form.blocks[0].exercises[1]).toMatchObject({
            prescribed_reps: 6,
            floor_is_derived: true,
            exercise_profile_id: 1,
            exercise_profile_fingerprint: 'recipe-strength',
        });
    });

    it('keeps a derived floor when a target override detaches the profile', () => {
        const editor = mountEditor({
            routine: routinePayload({ blocks: [], default_exercise_profile_id: 1 }),
            exercise_profiles: [strengthProfile],
        });
        editor.addBlock(false);
        const exercise = editor.form.blocks[0].exercises[0];

        editor.setExerciseTarget(exercise, '8');

        expect(exercise).toMatchObject({
            prescribed_reps: 8,
            achievement_floor: null,
            floor_is_derived: true,
            exercise_profile_id: null,
            exercise_profile_fingerprint: null,
        });
    });

    it('can sync eligible blocks when changing the routine profile', async () => {
        const current = routinePayload({
            default_exercise_profile_id: 1,
            blocks: [
                {
                    ...routinePayload().blocks[0],
                    shared_profile_id: 1,
                    shared_profile_fingerprint: 'shared-strength',
                    exercises: [
                        {
                            ...routinePayload().blocks[0].exercises[0],
                            exercise_profile_id: 1,
                            exercise_profile_fingerprint: 'recipe-strength',
                            floor_is_derived: true,
                        },
                    ],
                },
            ],
        });
        const editor = mountEditor({
            routine: current,
            exercise_profiles: [strengthProfile, hypertrophyProfile],
        });

        await editor.setRoutineProfile(2);

        expect(editor.form.default_exercise_profile_id).toBe(2);
        expect(editor.form.blocks[0]).toMatchObject({
            shared_profile_id: 2,
            shared_profile_fingerprint: 'shared-hypertrophy',
        });
        expect(editor.form.blocks[0].exercises[0]).toMatchObject({
            prescribed_reps: 10,
            exercise_profile_id: 2,
            exercise_profile_fingerprint: 'recipe-hypertrophy',
        });
        expect(editor.form.blocks[0].working.rest_seconds).toBe(90);
    });

    it('coerces string routine profile ids when syncing blocks', async () => {
        const current = routinePayload({
            default_exercise_profile_id: 1,
            blocks: [
                {
                    ...routinePayload().blocks[0],
                    shared_profile_id: 1,
                    shared_profile_fingerprint: 'shared-strength',
                    exercises: [
                        {
                            ...routinePayload().blocks[0].exercises[0],
                            exercise_profile_id: 1,
                            exercise_profile_fingerprint: 'recipe-strength',
                            floor_is_derived: true,
                        },
                    ],
                },
            ],
        });
        const editor = mountEditor({
            routine: current,
            exercise_profiles: [strengthProfile, hypertrophyProfile],
        });

        await editor.setRoutineProfile('2');

        expect(editor.form.default_exercise_profile_id).toBe(2);
        expect(editor.form.blocks[0].shared_profile_id).toBe(2);
    });

    it('save payload includes the updated routine default profile', async () => {
        const editor = mountEditor({
            routine: routinePayload({
                default_exercise_profile_id: 2,
                blocks: [],
            }),
            exercise_profiles: [strengthProfile, hypertrophyProfile],
        });

        await editor.setRoutineProfile(1);
        editor.save();

        expect(inertiaMocks().lastTransformed).toMatchObject({
            default_exercise_profile_id: 1,
        });
    });

    it('detects outdated shared and exercise profile assignments', () => {
        const current = routinePayload({
            blocks: [
                {
                    ...routinePayload().blocks[0],
                    shared_profile_id: 1,
                    shared_profile_fingerprint: 'old-shared',
                    exercises: [
                        {
                            ...routinePayload().blocks[0].exercises[0],
                            exercise_profile_id: 1,
                            exercise_profile_fingerprint: 'old-recipe',
                        },
                    ],
                },
            ],
        });
        const editor = mountEditor({
            routine: current,
            exercise_profiles: [strengthProfile],
        });

        expect(editor.sharedProfileIsOutdated(editor.form.blocks[0])).toBe(true);
        expect(editor.exerciseProfileIsOutdated(editor.form.blocks[0], 0)).toBe(true);
    });

    it('does not mark a mixed-profile exercise outdated when only the shared profile differs', () => {
        const current = routinePayload({
            blocks: [
                {
                    ...routinePayload().blocks[0],
                    shared_profile_id: 1,
                    shared_profile_fingerprint: 'old-shared',
                    exercises: [
                        {
                            ...routinePayload().blocks[0].exercises[0],
                            exercise_profile_id: 2,
                            exercise_profile_fingerprint: 'exercise-hypertrophy',
                        },
                    ],
                },
            ],
        });
        const editor = mountEditor({
            routine: current,
            exercise_profiles: [strengthProfile, hypertrophyProfile],
        });

        expect(editor.sharedProfileIsOutdated(editor.form.blocks[0])).toBe(true);
        expect(editor.exerciseProfileIsOutdated(editor.form.blocks[0], 0)).toBe(false);
    });

    it('leaves custom blocks unchanged when the routine profile changes', async () => {
        const customBlock = {
            ...routinePayload().blocks[0],
            shared_profile_id: null,
            shared_profile_fingerprint: null,
            working: { set_count: 3, rest_seconds: 45, dropsets: [] },
            exercises: [
                {
                    ...routinePayload().blocks[0].exercises[0],
                    prescribed_reps: 7,
                    exercise_profile_id: null,
                    exercise_profile_fingerprint: null,
                },
            ],
        };
        const editor = mountEditor({
            routine: routinePayload({
                default_exercise_profile_id: 1,
                blocks: [customBlock],
            }),
            exercise_profiles: [strengthProfile, hypertrophyProfile],
        });

        await editor.setRoutineProfile(2);

        expect(editor.form.blocks[0]).toMatchObject({
            shared_profile_id: null,
            working: { rest_seconds: 45 },
        });
        expect(editor.form.blocks[0].exercises[0]).toMatchObject({
            prescribed_reps: 7,
            exercise_profile_id: null,
        });
    });

    it('keeps dropsets collapsed by default and resets when changing blocks', async () => {
        const editor = mountEditor({
            routine: routinePayload({
                blocks: [
                    {
                        is_superset: false,
                        has_setup_after: false,
                        has_setup_after_warm_up: false,
                        exercises: [
                            {
                                exercise_id: 1,
                                working_weight_kg: 60,
                                prescribed_reps: 6,
                                achievement_floor: null,
                                progression_target: null,
                                deload_exercise_id: null,
                                deload_working_weight_kg: null,
                            },
                        ],
                        working: {
                            set_count: 3,
                            rest_seconds: 120,
                            dropsets: [{ set_index: 0, segments: [{ weight_kg: 60 }, { weight_kg: 50 }] }],
                        },
                        warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
                    },
                    {
                        is_superset: false,
                        has_setup_after: false,
                        has_setup_after_warm_up: false,
                        exercises: [
                            {
                                exercise_id: 2,
                                working_weight_kg: 60,
                                prescribed_reps: 6,
                                achievement_floor: null,
                                progression_target: null,
                                deload_exercise_id: null,
                                deload_working_weight_kg: null,
                            },
                        ],
                        working: { set_count: 3, rest_seconds: 120, dropsets: [] },
                        warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
                    },
                ],
            }),
        });
        expect(editor.dropsetsExpanded.value).toBe(false);
        editor.toggleDropsetsExpanded();
        expect(editor.dropsetsExpanded.value).toBe(true);
        editor.active.value = 1;
        await nextTick();
        expect(editor.dropsetsExpanded.value).toBe(false);
    });

    it('keeps progression collapsed by default and resets when changing blocks', async () => {
        const editor = mountEditor({
            routine: routinePayload({
                blocks: [
                    {
                        is_superset: false,
                        has_setup_after: false,
                        has_setup_after_warm_up: false,
                        exercises: [
                            {
                                exercise_id: 1,
                                working_weight_kg: 60,
                                prescribed_reps: 6,
                                achievement_floor: null,
                                progression_target: null,
                                deload_exercise_id: null,
                                deload_working_weight_kg: null,
                            },
                        ],
                        working: { set_count: 3, rest_seconds: 120, dropsets: [] },
                        warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
                    },
                    {
                        is_superset: false,
                        has_setup_after: false,
                        has_setup_after_warm_up: false,
                        exercises: [
                            {
                                exercise_id: 2,
                                working_weight_kg: 60,
                                prescribed_reps: 6,
                                achievement_floor: null,
                                progression_target: null,
                                deload_exercise_id: null,
                                deload_working_weight_kg: null,
                            },
                        ],
                        working: { set_count: 3, rest_seconds: 120, dropsets: [] },
                        warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
                    },
                ],
            }),
            achievement_floor_default: 1,
            progression_target_default: 6,
        });
        expect(editor.progressionExpanded.value).toBe(false);
        expect(editor.achievementFloorDefault.value).toBe(1);
        expect(editor.progressionTargetDefault.value).toBe(6);
        editor.toggleProgressionExpanded();
        expect(editor.progressionExpanded.value).toBe(true);
        editor.active.value = 1;
        await nextTick();
        expect(editor.progressionExpanded.value).toBe(false);
    });

    it('resolves exercise names from the catalog', () => {
        const editor = mountEditor();
        expect(editor.exerciseName(2)).toBe('Row');
        expect(editor.exerciseName(null)).toBe('Exercise');
    });

    it('submits routine update via inertia form', () => {
        const editor = mountEditor();
        editor.save();
        expect(inertiaMocks().inertiaFormPut).toHaveBeenCalled();
    });

    it('sends empty rest fields as 0', () => {
        const editor = mountEditor();
        editor.addBlock(false);
        // Cleared number inputs leave '' / NaN from v-model.number
        (editor.form.blocks[0].working as { rest_seconds: unknown }).rest_seconds = '';
        (editor.form.blocks[0].warm_up as { rest_seconds: unknown }).rest_seconds = Number.NaN;

        editor.save();

        const payload = inertiaMocks().lastTransformed as {
            blocks: Array<{ working: { rest_seconds: number }; warm_up: { rest_seconds: number } }>;
        };
        expect(payload.blocks[0].working.rest_seconds).toBe(0);
        expect(payload.blocks[0].warm_up.rest_seconds).toBe(0);
    });

    it('scrolls save errors into view when validation fails', async () => {
        const scrollIntoView = vi.fn();
        const target = document.createElement('div');
        target.scrollIntoView = scrollIntoView;
        vi.spyOn(document, 'querySelector').mockReturnValue(target);

        const editor = mountEditor();
        editor.save();

        const options = inertiaMocks().inertiaFormPut.mock.calls[0][1] as { onError?: () => void };
        expect(options.onError).toEqual(expect.any(Function));
        options.onError?.();
        await vi.waitFor(() => expect(scrollIntoView).toHaveBeenCalled());
    });

    it('guards duplicate against double-submit', async () => {
        inertiaMocks().routerMocks.post.mockImplementation((_url, _data, options) => {
            expect(options?.onFinish).toEqual(expect.any(Function));
        });
        const editor = mountEditor();
        await editor.duplicateRoutine();
        expect(editor.mutating.value).toBe(true);
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledTimes(1);

        await editor.duplicateRoutine();
        expect(inertiaMocks().routerMocks.post).toHaveBeenCalledTimes(1);

        inertiaMocks().routerMocks.post.mock.calls[0][2].onFinish();
        expect(editor.mutating.value).toBe(false);
    });

    it('guards delete against double-submit', async () => {
        inertiaMocks().routerMocks.delete.mockImplementation((_url, options) => {
            expect(options?.onFinish).toEqual(expect.any(Function));
        });
        const editor = mountEditor();
        await editor.deleteRoutine();
        expect(editor.mutating.value).toBe(true);
        expect(inertiaMocks().routerMocks.delete).toHaveBeenCalledTimes(1);

        await editor.deleteRoutine();
        expect(inertiaMocks().routerMocks.delete).toHaveBeenCalledTimes(1);

        inertiaMocks().routerMocks.delete.mock.calls[0][1].onFinish();
        expect(editor.mutating.value).toBe(false);
    });
});
