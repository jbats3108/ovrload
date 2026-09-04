import { plateProfile } from '@/test/factories';
import PlateGuideCard from '@/workouts/components/PlateGuideCard.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const exactLoad = {
    bar_g: 20_000,
    per_side: [{ denomination_g: 20_000, count: 1, colour: null }],
    total_g: 60_000,
    exact: true,
    delta_g: 0,
};

describe('PlateGuideCard', () => {
    it('emits applyNearest when the target weight is not loadable', async () => {
        const wrapper = mount(PlateGuideCard, {
            props: {
                plateLoad: {
                    bar_g: 20_000,
                    per_side: [{ denomination_g: 20_000, count: 1, colour: null }],
                    total_g: 60_000,
                    exact: false,
                    delta_g: -2500,
                },
                formatPlateStack: '20 bar + 1×20 / side',
                weightUnit: 'kg',
                plateProfile: plateProfile(),
            },
        });

        const nearestButton = wrapper.findAll('button').find((button) => button.text() === 'Use nearest');
        expect(nearestButton).toBeDefined();
        await nearestButton?.trigger('click');

        expect(wrapper.emitted('applyNearest')).toHaveLength(1);
    });

    it('hides the nearest action when the weight is already loadable', () => {
        const wrapper = mount(PlateGuideCard, {
            props: {
                plateLoad: exactLoad,
                formatPlateStack: '20 bar + 1×20 / side',
                weightUnit: 'kg',
                plateProfile: plateProfile(),
            },
        });

        expect(wrapper.findAll('button').some((button) => button.text() === 'Use nearest')).toBe(false);
        expect(wrapper.findAll('button').some((button) => button.text() === 'Edit plates')).toBe(true);
    });

    it('keeps per-side controls collapsed until Edit plates is toggled', async () => {
        const wrapper = mount(PlateGuideCard, {
            props: {
                plateLoad: exactLoad,
                formatPlateStack: '20 bar + 1×20 / side',
                weightUnit: 'kg',
                plateProfile: plateProfile(),
            },
        });

        expect(wrapper.find('button[aria-label="Add 10kg plate per side"]').exists()).toBe(false);

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Edit plates')
            ?.trigger('click');

        expect(wrapper.find('button[aria-label="Add 10kg plate per side"]').exists()).toBe(true);
        expect(wrapper.findAll('button').some((button) => button.text() === 'Done editing')).toBe(true);
        expect(wrapper.find('[data-testid="plate-editor"]').exists()).toBe(true);
    });

    it('emits per-side plate changes after expanding edit controls', async () => {
        const wrapper = mount(PlateGuideCard, {
            props: {
                plateLoad: exactLoad,
                formatPlateStack: '20 bar + 1×20 / side',
                weightUnit: 'kg',
                plateProfile: plateProfile(),
            },
        });

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Edit plates')
            ?.trigger('click');
        await wrapper.find('button[aria-label="Add 10kg plate per side"]').trigger('click');

        expect(wrapper.emitted('changePlate')).toEqual([[10_000, 1]]);
    });
});
