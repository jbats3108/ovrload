import DesktopBlockList from '@/routines/components/DesktopBlockList.vue';
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

function mountList(props: Partial<EditRoutineProps> = {}) {
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

            return () => h(DesktopBlockList);
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

describe('DesktopBlockList', () => {
    it('places a Dropsets disclosure under each non-superset block', () => {
        const { wrapper } = mountList();
        const settings = document.body.querySelector('[data-desktop-routine-settings]');
        const dropsets = document.body.querySelector('[data-dropset-editor]');

        expect(settings).toBeTruthy();
        expect(dropsets).toBeTruthy();
        expect(dropsets?.closest('tbody')).toBeTruthy();
        expect(settings!.compareDocumentPosition(dropsets!) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(dropsets?.textContent).toContain('Dropsets');
        expect(dropsets?.textContent).not.toContain('Set 1');

        wrapper.unmount();
    });

    it('reveals Routine profile and Deload after expanding Routine settings', async () => {
        const { wrapper } = mountList();

        const settings = document.body.querySelector('[data-desktop-routine-settings]');
        settings!.querySelector('button')!.click();
        await nextTick();

        expect(document.body.querySelector('[data-routine-deload]')).toBeTruthy();
        expect(settings?.textContent).toContain('Routine profile');

        wrapper.unmount();
    });

    it('hides Target, Floor, and Rest editors while profiles are assigned', () => {
        const { wrapper } = mountList({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        expect(document.body.querySelectorAll('[data-exercise-floor]')).toHaveLength(0);
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);
        expect(document.body.textContent).toContain('Target 6 · Floor 4');
        expect(document.body.textContent).toContain('Target 10 · Floor 8');
        expect(document.body.querySelector('[data-shared-rest-summary]')?.textContent).toContain('3m');
        expect(document.body.querySelector('[data-shared-warmup-summary]')?.textContent).toContain('No warm-up');
        expect(document.body.textContent).toContain('Rest');
        expect(document.body.textContent).toContain('Warm-up');
        expect(document.body.textContent).not.toContain('Rest & warm-up');
        expect(document.body.textContent).toContain('Setup before next exercise');
        expect(document.body.textContent).not.toContain('Setup→next');
        expect(document.body.querySelector('[data-dropset-editor]')).toBeNull();
        expect(document.body.querySelector('[data-swap-superset]')?.textContent).toContain('Swap A↔B');

        wrapper.unmount();
    });

    it('swaps A and B when Swap A↔B is clicked', async () => {
        const { wrapper, editor } = mountList({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        try {
            const before = editor.form.blocks[0].exercises.map((exercise) => exercise.exercise_id);
            expect(before).toHaveLength(2);
            expect(before[0]).not.toBe(before[1]);

            document.body.querySelector<HTMLButtonElement>('[data-swap-superset]')!.click();
            await nextTick();

            expect(editor.form.blocks[0].exercises.map((exercise) => exercise.exercise_id)).toEqual([before[1], before[0]]);
        } finally {
            wrapper.unmount();
        }
    });

    it('shows Deload alternate while a profile is assigned', () => {
        const { wrapper } = mountList({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        expect(document.body.querySelectorAll('[data-deload-alternate]')).toHaveLength(2);
        expect(document.body.querySelectorAll('[data-exercise-target]')).toHaveLength(0);

        wrapper.unmount();
    });

    it('reveals Target and Floor after Customise', async () => {
        const { wrapper } = mountList({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        const customise = document.body.querySelectorAll<HTMLButtonElement>('[data-customise-exercise]')[0];
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
        const { wrapper, editor } = mountList({
            achievement_floor_default: 1,
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        document.body.querySelectorAll<HTMLButtonElement>('[data-customise-exercise]')[0]!.click();
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
        const { wrapper } = mountList({
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        expect(document.body.textContent).not.toContain('Working rest (s)');
        expect(document.body.textContent).not.toContain('Enable warm-up');

        document.body.querySelectorAll<HTMLButtonElement>('[data-customise-shared]')[0]!.click();
        await nextTick();

        expect(document.body.textContent).toContain('Working rest (s)');
        expect(document.body.querySelector('[data-warmup-disabled]')?.textContent).toContain('Enable warm-up');
        expect(document.body.querySelector('[data-warmup-editor]')).toBeNull();
        expect(document.body.querySelector('[data-shared-rest-summary]')).toBeNull();
        expect(document.body.querySelector('[data-shared-warmup-summary]')).toBeNull();

        wrapper.unmount();
    });

    it('restores shared rest after Cancel Customise', async () => {
        const { wrapper, editor } = mountList({
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        document.body.querySelectorAll<HTMLButtonElement>('[data-customise-shared]')[0]!.click();
        await nextTick();

        editor.form.blocks[0].working.rest_seconds = 45;
        await nextTick();

        document.body.querySelector<HTMLButtonElement>('[data-cancel-customise-shared]')!.click();
        await nextTick();

        expect(editor.form.blocks[0].shared_profile_id).toBe(1);
        expect(editor.form.blocks[0].working.rest_seconds).toBe(180);
        expect(document.body.querySelector('[data-shared-rest-summary]')).toBeTruthy();

        wrapper.unmount();
    });

    it('expands the warm-up editor only after Enable warm-up', async () => {
        const { wrapper } = mountList({
            exercise_profiles: [strength, hypertrophy],
            routine: profiledSupersetRoutine(),
        });

        document.body.querySelectorAll<HTMLButtonElement>('[data-customise-shared]')[0]!.click();
        await nextTick();

        document.body.querySelector<HTMLButtonElement>('[data-enable-warmup]')!.click();
        await nextTick();

        expect(document.body.querySelector('[data-warmup-editor]')).toBeTruthy();
        expect(document.body.textContent).toContain('Warm-up rest (s)');
        expect(document.body.textContent).toContain('+ Step');

        document.body.querySelector<HTMLButtonElement>('[data-disable-warmup]')!.click();
        await nextTick();

        expect(document.body.querySelector('[data-warmup-disabled]')?.textContent).toContain('Enable warm-up');
        expect(document.body.querySelector('[data-warmup-editor]')).toBeNull();

        wrapper.unmount();
    });

    it('keeps dropsets collapsed under their block until expanded', async () => {
        const { wrapper } = mountList();

        const dropsetRow = document.body.querySelector('[data-dropset-editor]');
        expect(dropsetRow).toBeTruthy();
        expect(dropsetRow?.textContent).not.toContain('Set 1');

        dropsetRow!.querySelector('button')!.click();
        await nextTick();

        expect(dropsetRow?.textContent).toContain('Set 1');

        wrapper.unmount();
    });
});
