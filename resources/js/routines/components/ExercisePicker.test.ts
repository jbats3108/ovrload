import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import { createRoutineEditor, routineEditorKey } from '@/routines/composables/useRoutineEditor';
import { exerciseOption, routinePayload } from '@/test/factories';
import { inertiaMocks } from '@/test/inertiaMocks';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, provide, ref } from 'vue';

function mountPicker() {
    const exerciseId = ref<number | null>(1);
    let editor!: ReturnType<typeof createRoutineEditor>;
    const Host = defineComponent({
        setup() {
            editor = createRoutineEditor({
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
                                },
                            ],
                            working: { set_count: 3, rest_seconds: 120, dropsets: [] },
                            warm_up: { set_count: 0, rest_seconds: 60, steps: [] },
                        },
                    ],
                }),
                exercises: [exerciseOption(), exerciseOption({ id: 2, name: 'Row', primary_muscle_group: 'Back' })],
                weight_unit: 'kg',
                warm_up_defaults: [],
                muscle_groups: [
                    { name: 'Chest', slug: 'chest' },
                    { name: 'Back', slug: 'back' },
                ],
                equipment_options: [{ value: 'cable', label: 'Cable' }],
            });
            provide(routineEditorKey, editor);
            return () =>
                h(ExercisePicker, {
                    modelValue: exerciseId.value,
                    'onUpdate:modelValue': (id: number | null) => {
                        exerciseId.value = id;
                    },
                });
        },
    });
    const wrapper = mount(Host, { attachTo: document.body });
    return { wrapper, editor, exerciseId };
}

describe('ExercisePicker', () => {
    beforeEach(() => {
        inertiaMocks().inertiaFormPut.mockClear();
    });

    it('shows the current exercise and picks from search results', async () => {
        const { wrapper, exerciseId } = mountPicker();
        expect(wrapper.text()).toContain('Bench Press');

        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');
        await nextTick();

        const search = document.body.querySelector('input[inputmode="search"]') as HTMLInputElement | null;
        expect(search).not.toBeNull();
        search!.value = 'row';
        search!.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();

        const match = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.includes('Row'));
        expect(match).toBeTruthy();
        match!.click();
        await nextTick();

        expect(exerciseId.value).toBe(2);
    });

    it('filters during composition (mobile mid-word typing)', async () => {
        const { wrapper } = mountPicker();
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');
        await nextTick();

        const search = document.body.querySelector('input[inputmode="search"]') as HTMLInputElement;
        search.value = 'ro';
        search.dispatchEvent(new CompositionEvent('compositionupdate', { data: 'ro', bubbles: true }));
        await nextTick();

        const count = document.body.textContent?.match(/(\d+) of 2/)?.[1];
        expect(count).toBe('1');
        const listButtons = Array.from(document.body.querySelectorAll('ul button')).map((b) => b.textContent);
        expect(listButtons.some((t) => t?.includes('Row'))).toBe(true);
        expect(listButtons.some((t) => t?.includes('Bench Press'))).toBe(false);
    });

    it('opens create custom form from the header action', async () => {
        const { wrapper } = mountPicker();
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');
        await nextTick();

        const createBtn = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.includes('Create custom'));
        expect(createBtn).toBeTruthy();
        createBtn!.click();
        await nextTick();

        expect(document.body.textContent).toContain('New custom exercise');
        expect(document.body.querySelector('input[maxlength="255"]')).not.toBeNull();
    });
});
