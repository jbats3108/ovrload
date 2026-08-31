import DesktopBlockList from '@/routines/components/DesktopBlockList.vue';
import { createRoutineEditor, routineEditorKey } from '@/routines/composables/useRoutineEditor';
import { exerciseOption, routinePayload } from '@/test/factories';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, provide } from 'vue';

function mountList() {
    let editor!: ReturnType<typeof createRoutineEditor>;
    const Host = defineComponent({
        setup() {
            editor = createRoutineEditor({
                routine: routinePayload(),
                exercises: [exerciseOption()],
                weight_unit: 'kg',
                warm_up_defaults: [],
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
    it('places the routine Deload recipe above the dropset editor', () => {
        const { wrapper } = mountList();
        const deload = document.body.querySelector('[data-routine-deload]');
        const dropsets = document.body.querySelector('[data-dropset-editor]');

        expect(deload).toBeTruthy();
        expect(dropsets).toBeTruthy();
        expect(deload!.compareDocumentPosition(dropsets!) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

        wrapper.unmount();
    });
});
