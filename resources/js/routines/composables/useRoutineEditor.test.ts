import { createRoutineEditor } from '@/routines/composables/useRoutineEditor';
import * as confirmDialog from '@/shared/lib/confirmDialog';
import { exerciseOption, routinePayload } from '@/test/factories';
import { inertiaMocks } from '@/test/inertiaMocks';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

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
        expect(editor.progressionTargetDefault.value).toBeNull();
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
