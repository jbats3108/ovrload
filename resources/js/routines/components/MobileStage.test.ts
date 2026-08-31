import MobileStage from '@/routines/components/MobileStage.vue';
import { createRoutineEditor, routineEditorKey, type EditRoutineProps } from '@/routines/composables/useRoutineEditor';
import type { ExerciseProfileOption } from '@/settings/types';
import { exerciseOption, routinePayload } from '@/test/factories';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, provide } from 'vue';

const strength: ExerciseProfileOption = {
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
    warm_up_steps: [],
    recipe_fingerprint: 'recipe-strength',
    exercise_fingerprint: 'exercise-strength',
    shared_fingerprint: 'shared-strength',
    reference_count: 0,
    stale_assignment_count: 0,
    is_default: true,
    assigned_routines: [],
};

const hypertrophy: ExerciseProfileOption = {
    ...strength,
    id: 2,
    slug: 'preset-hypertrophy',
    name: 'Hypertrophy',
    display_name: 'OVRLOAD Hypertrophy',
    target_reps: 10,
    floor: 8,
    working_rest_seconds: 90,
    recipe_fingerprint: 'recipe-hypertrophy',
    exercise_fingerprint: 'exercise-hypertrophy',
    shared_fingerprint: 'shared-hypertrophy',
    is_default: false,
};

function mountStage(props: Partial<EditRoutineProps> = {}) {
    let editor!: ReturnType<typeof createRoutineEditor>;
    const Host = defineComponent({
        setup() {
            editor = createRoutineEditor({
                routine: routinePayload(),
                exercises: [exerciseOption(), exerciseOption({ id: 2, name: 'Row' })],
                weight_unit: 'kg',
                warm_up_defaults: [],
                ...props,
            });
            provide(routineEditorKey, editor);

            return () => h(MobileStage);
        },
    });

    const wrapper = mount(Host, {
        attachTo: document.body,
        global: {
            mocks: {
                route: (name: string) => `/${name}`,
            },
        },
    });

    return { wrapper, editor };
}

describe('MobileStage', () => {
    it('keeps Cancel/Save in document flow under the stage content', () => {
        const { wrapper } = mountStage();
        const save = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Save');
        expect(save).toBeTruthy();

        const bar = save!.closest('.max-w-lg');
        expect(bar).toBeTruthy();
        expect(bar?.className).not.toContain('fixed');

        wrapper.unmount();
    });

    it('shows save validation errors above the mobile Save actions', async () => {
        const { wrapper, editor } = mountStage();
        Object.assign(editor.form.errors, {
            'blocks.0.working.rest_seconds': 'The rest seconds field is required.',
        });
        await nextTick();

        const alert = document.body.querySelector('[data-routine-save-errors]');
        expect(alert?.textContent).toContain("Couldn't save");
        expect(alert?.textContent).toContain('The rest seconds field is required.');

        const save = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Save');
        expect(save?.closest('.max-w-lg')?.contains(alert)).toBe(true);

        wrapper.unmount();
    });

    it('shows a Floor input per superset exercise with that profile as the placeholder', () => {
        const base = routinePayload().blocks[0].exercises[0];
        const { wrapper } = mountStage({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: routinePayload({
                blocks: [
                    {
                        ...routinePayload().blocks[0],
                        is_superset: true,
                        shared_profile_id: 1,
                        shared_profile_fingerprint: 'shared-strength',
                        exercises: [
                            {
                                ...base,
                                prescribed_reps: 6,
                                achievement_floor: null,
                                floor_is_derived: true,
                                exercise_profile_id: 1,
                                exercise_profile_fingerprint: 'exercise-strength',
                            },
                            {
                                ...base,
                                exercise_id: 2,
                                prescribed_reps: 10,
                                achievement_floor: null,
                                floor_is_derived: true,
                                exercise_profile_id: 2,
                                exercise_profile_fingerprint: 'exercise-hypertrophy',
                            },
                        ],
                    },
                ],
            }),
        });

        const floors = Array.from(document.body.querySelectorAll<HTMLInputElement>('[data-exercise-floor]'));
        expect(floors).toHaveLength(2);
        expect(floors[0]?.placeholder).toBe('4');
        expect(floors[1]?.placeholder).toBe('8');

        const targets = Array.from(document.body.querySelectorAll<HTMLInputElement>('[data-exercise-target]'));
        expect(targets.map((input) => input.value)).toEqual(['6', '10']);

        wrapper.unmount();
    });

    it('places the routine Deload recipe above the dropset editor', () => {
        const { wrapper } = mountStage();
        const deload = document.body.querySelector('[data-routine-deload]');
        const dropsets = document.body.querySelector('[data-dropset-editor]');

        expect(deload).toBeTruthy();
        expect(dropsets).toBeTruthy();
        expect(deload!.compareDocumentPosition(dropsets!) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

        wrapper.unmount();
    });
});
