import { exerciseOption, plateProfile, playerBlock, playerSet, workoutPayload } from '@/test/factories';
import PlayerHeader from '@/workouts/components/PlayerHeader.vue';
import { createWorkoutPlayer, workoutPlayerKey } from '@/workouts/composables/useWorkoutPlayer';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, provide } from 'vue';

function mountHeader() {
    const Host = defineComponent({
        setup() {
            const player = createWorkoutPlayer({
                workout: workoutPayload({
                    routine_name: 'Barbell Strength',
                    blocks: [playerBlock({ sets: [playerSet({ id: 1, completed: false })] })],
                }),
                plate_profile: plateProfile(),
                exercises: [exerciseOption({ id: 2, name: 'Row', primary_muscle_group: 'Back' })],
            });
            provide(workoutPlayerKey, player);
            return () => h(PlayerHeader);
        },
    });

    return mount(Host, { attachTo: document.body });
}

describe('PlayerHeader', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => `/${String(name)}`),
        );
    });

    it('keeps finish, abandon, and leave on one row and add exercise below', () => {
        const wrapper = mountHeader();
        const buttons = wrapper.findAll('button').map((button) => button.text().trim());

        expect(buttons).toContain('Add exercise to session');
        expect(buttons).toContain('Finish');
        expect(buttons).toContain('Abandon');
        expect(buttons).toContain('Leave');
        expect(buttons.indexOf('Finish')).toBeLessThan(buttons.indexOf('Abandon'));
        expect(buttons.indexOf('Abandon')).toBeLessThan(buttons.indexOf('Leave'));
        expect(wrapper.get('header').classes()).toContain('min-w-0');
        expect(wrapper.get('header').html()).toContain('flex-nowrap');
    });
});
