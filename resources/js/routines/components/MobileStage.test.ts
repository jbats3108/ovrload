import MobileStage from '@/routines/components/MobileStage.vue';
import RoutineEditorHeader from '@/routines/components/RoutineEditorHeader.vue';
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

function mountWithEditor(Component: ReturnType<typeof defineComponent>, props: Partial<EditRoutineProps> = {}) {
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

            return () => h(Component);
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

function mountStage(props: Partial<EditRoutineProps> = {}) {
    return mountWithEditor(MobileStage, props);
}

async function openFirstExerciseTab(): Promise<void> {
    const tabs = document.body.querySelector('[data-mobile-stage-tabs]');
    const exerciseTab = tabs?.querySelectorAll('button')[1] as HTMLButtonElement | undefined;
    expect(exerciseTab).toBeTruthy();
    exerciseTab!.click();
    await nextTick();
}

function profiledSupersetRoutine() {
    const base = routinePayload().blocks[0].exercises[0];

    return routinePayload({
        blocks: [
            {
                ...routinePayload().blocks[0],
                is_superset: true,
                shared_profile_id: 1,
                shared_profile_fingerprint: 'shared-strength',
                working: { ...routinePayload().blocks[0].working, rest_seconds: 180 },
                warm_up: {
                    set_count: 0,
                    rest_seconds: 60,
                    steps: [],
                },
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
    });
}

describe('MobileStage', () => {
    it('opens on the Routine sheet with name, profile, and Deload', () => {
        const { wrapper } = mountStage();

        expect(document.body.querySelector('[data-routine-pane]')).toBeTruthy();
        expect(document.body.querySelector('[data-routine-deload]')).toBeTruthy();
        expect(document.body.querySelector('[data-routine-pane-tab]')?.className).toContain('border-primary');
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);

        wrapper.unmount();
    });

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

    it('hides Target and Floor inputs while a profile is assigned', async () => {
        const { wrapper } = mountStage({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        expect(document.body.querySelector('[data-routine-pane]')).toBeNull();
        expect(document.body.querySelector('[data-routine-deload]')).toBeNull();
        expect(document.body.querySelectorAll('[data-exercise-floor]')).toHaveLength(0);
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);
        expect(document.body.textContent).toContain('Target 6 · Floor 4');
        expect(document.body.textContent).toContain('Target 10 · Floor 8');
        expect(document.body.querySelector('[data-shared-recipe-summary]')?.textContent).toContain('Rest 3m');

        wrapper.unmount();
    });

    it('shows Deload alternate while a profile is assigned', async () => {
        const { wrapper } = mountStage({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        expect(document.body.querySelectorAll('[data-deload-alternate]')).toHaveLength(2);
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);

        wrapper.unmount();
    });

    it('reveals Target and Floor after Customise', async () => {
        const { wrapper } = mountStage({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        const customise = document.body.querySelector<HTMLButtonElement>('[data-customise-exercise]');
        expect(customise).toBeTruthy();
        customise!.click();
        await nextTick();

        const floors = Array.from(document.body.querySelectorAll<HTMLInputElement>('[data-exercise-floor]'));
        expect(floors).toHaveLength(1);
        expect(floors[0]?.placeholder).toBe('4');

        const targets = Array.from(document.body.querySelectorAll<HTMLInputElement>('[data-exercise-target]'));
        expect(targets.map((input) => input.value)).toEqual(['6']);

        wrapper.unmount();
    });

    it('restores the profile snapshot after Cancel Customise', async () => {
        const { wrapper, editor } = mountStage({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        document.body.querySelector<HTMLButtonElement>('[data-customise-exercise]')!.click();
        await nextTick();

        const exercise = editor.form.blocks[0].exercises[0];
        exercise.deload_exercise_id = 2;
        exercise.deload_working_weight_kg = 42;
        editor.setExerciseTarget(exercise, '9');
        await nextTick();

        document.body.querySelector<HTMLButtonElement>('[data-cancel-customise-exercise]')!.click();
        await nextTick();

        expect(exercise.exercise_profile_id).toBe(1);
        expect(exercise.prescribed_reps).toBe(6);
        expect(exercise.deload_exercise_id).toBe(2);
        expect(exercise.deload_working_weight_kg).toBe(42);
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);
        expect(document.body.textContent).toContain('Target 6 · Floor 4');

        wrapper.unmount();
    });

    it('reveals Rest and Warm-up editors after Customise on shared recipe', async () => {
        const { wrapper } = mountStage({
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        expect(document.body.textContent).not.toContain('Compact (40%×5, 60%×3)');

        document.body.querySelector<HTMLButtonElement>('[data-customise-shared]')!.click();
        await nextTick();

        expect(document.body.textContent).toContain('Compact (40%×5, 60%×3)');
        expect(document.body.querySelector('[data-shared-recipe-summary]')).toBeNull();

        wrapper.unmount();
    });

    it('restores shared rest after Cancel Customise', async () => {
        const { wrapper, editor } = mountStage({
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        await openFirstExerciseTab();

        document.body.querySelector<HTMLButtonElement>('[data-customise-shared]')!.click();
        await nextTick();

        editor.form.blocks[0].working.rest_seconds = 45;
        await nextTick();

        document.body.querySelector<HTMLButtonElement>('[data-cancel-customise-shared]')!.click();
        await nextTick();

        expect(editor.form.blocks[0].shared_profile_id).toBe(1);
        expect(editor.form.blocks[0].working.rest_seconds).toBe(180);
        expect(document.body.querySelector('[data-shared-recipe-summary]')).toBeTruthy();

        wrapper.unmount();
    });
});

describe('RoutineEditorHeader', () => {
    it('keeps Routine profile and Deload out of the header (desktop uses Routine settings; mobile uses the Routine sheet)', () => {
        const { wrapper } = mountWithEditor(RoutineEditorHeader);
        expect(document.body.querySelector('[data-routine-deload]')).toBeNull();
        expect(document.body.textContent).not.toContain('Routine profile');
        wrapper.unmount();
    });
});
