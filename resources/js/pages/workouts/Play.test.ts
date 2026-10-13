import Play from '@/pages/workouts/Play.vue';
import { plateProfile, playerBlock, playerSet, workoutPayload } from '@/test/factories';
import CompleteStage from '@/workouts/components/CompleteStage.vue';
import PlayerHeader from '@/workouts/components/PlayerHeader.vue';
import SetStage from '@/workouts/components/SetStage.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('Play', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => `/${String(name)}`),
        );
        Object.defineProperty(navigator, 'wakeLock', {
            configurable: true,
            value: { request: vi.fn().mockRejectedValue(new Error('unsupported')) },
        });
    });

    it('hides the player header so complete covers the full page', () => {
        const wrapper = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [playerSet({ id: 1, completed: true, logged_weight_kg: 100, logged_reps: 5 })],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });

        expect(wrapper.findComponent(PlayerHeader).exists()).toBe(false);
        expect(wrapper.findComponent(CompleteStage).exists()).toBe(true);
        expect(wrapper.text()).toContain('All sets logged');
    });

    it('puts set progress in the exercise name header', () => {
        const wrapper = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [
                                playerSet({ id: 1, set_index: 0, completed: true, logged_weight_kg: 100, logged_reps: 5 }),
                                playerSet({ id: 2, set_index: 1, completed: false }),
                                playerSet({ id: 3, set_index: 2, completed: false }),
                                playerSet({ id: 4, set_index: 3, completed: false }),
                            ],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });

        const stage = wrapper.findComponent(SetStage);
        expect(stage.exists()).toBe(true);
        expect(stage.text()).toContain('Squat');
        expect(stage.text()).toContain('Working 2 of 4');
        expect(stage.get('h2').text()).toBe('Squat');
        expect(stage.text()).toMatch(/Squat[\s\S]*Working 2 of 4/);
    });

    it('labels warm-up set progress in the exercise header', () => {
        const wrapper = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [
                                playerSet({
                                    id: 1,
                                    group_type: 'warm_up',
                                    set_index: 0,
                                    completed: true,
                                    logged_weight_kg: 40,
                                    logged_reps: 5,
                                }),
                                playerSet({ id: 2, group_type: 'warm_up', set_index: 1, completed: false, target_weight_kg: 60 }),
                                playerSet({ id: 3, set_index: 0, completed: false }),
                            ],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });

        const stage = wrapper.findComponent(SetStage);
        expect(stage.exists()).toBe(true);
        expect(stage.text()).toContain('Warm-up 2 of 2');
        expect(stage.text()).not.toContain('Working 2 of 2');
    });

    it('puts add exercise in the player header, not the set stage', () => {
        const wrapper = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [playerSet({ id: 1, completed: false })],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });

        const header = wrapper.findComponent(PlayerHeader);
        const stage = wrapper.findComponent(SetStage);

        expect(header.exists()).toBe(true);
        expect(header.text()).toContain('Add exercise to session');
        expect(header.text()).toContain('Finish');
        expect(header.text()).toContain('Abandon');
        expect(header.text()).toContain('Leave');
        expect(stage.exists()).toBe(true);
        expect(stage.text()).not.toContain('Add exercise to session');
        expect(wrapper.element.className).toContain('overflow-hidden');
        expect(wrapper.element.className).toContain('h-dvh');
    });

    it('offers Target hit and Edit on working sets, and Floor hit when floor exists', () => {
        const withoutFloor = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [playerSet({ id: 1, completed: false })],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });
        const withoutFloorStage = withoutFloor.findComponent(SetStage);
        expect(withoutFloorStage.text()).toContain('Target hit');
        expect(withoutFloorStage.text()).toContain('Edit');
        expect(withoutFloorStage.text()).not.toContain('Floor hit');
        expect(withoutFloorStage.text()).not.toMatch(/\bDone\b/);

        const withFloor = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            exercises: [
                                {
                                    id: 10,
                                    name: 'Squat',
                                    working_weight_kg: 100,
                                    note: null,
                                    prescribed_reps: 5,
                                    achievement_floor: 3,
                                    progression_target: null,
                                    position: 0,
                                },
                            ],
                            sets: [playerSet({ id: 1, completed: false })],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });
        expect(withFloor.findComponent(SetStage).text()).toContain('Floor hit');
    });

    it('labels warm-up quick-confirm as Done and hides Floor hit', () => {
        const wrapper = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            exercises: [
                                {
                                    id: 10,
                                    name: 'Squat',
                                    working_weight_kg: 100,
                                    note: null,
                                    prescribed_reps: 5,
                                    achievement_floor: 3,
                                    progression_target: null,
                                    position: 0,
                                },
                            ],
                            sets: [playerSet({ id: 1, group_type: 'warm_up', completed: false, target_weight_kg: 60 })],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });
        const stage = wrapper.findComponent(SetStage);
        const text = stage.text();
        expect(text).toContain('Done');
        expect(text).toContain('Edit');
        expect(text).not.toContain('Target hit');
        expect(text).not.toContain('Floor hit');
    });

    it('keeps Done for dropsets and timed sets', () => {
        const dropset = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [
                                playerSet({
                                    id: 1,
                                    is_dropset: true,
                                    segments: [
                                        { position: 0, weight_kg: 80 },
                                        { position: 1, weight_kg: 70 },
                                    ],
                                }),
                            ],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });
        expect(dropset.findComponent(SetStage).text()).toContain('Done');
        expect(dropset.findComponent(SetStage).text()).not.toContain('Target hit');

        const timed = mount(Play, {
            props: {
                workout: workoutPayload({
                    blocks: [
                        playerBlock({
                            sets: [
                                playerSet({
                                    id: 1,
                                    prescription_mode: 'duration',
                                    target_duration_seconds: 30,
                                    target_reps: null,
                                }),
                            ],
                        }),
                    ],
                }),
                plate_profile: plateProfile(),
            },
        });
        expect(timed.findComponent(SetStage).text()).toContain('Done');
        expect(timed.findComponent(SetStage).text()).not.toContain('Target hit');
    });
});
