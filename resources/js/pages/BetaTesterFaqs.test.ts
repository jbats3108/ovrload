import BetaTesterFaqs from '@/pages/BetaTesterFaqs.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

describe('BetaTesterFaqs', () => {
    it('frames the product around wants and links to About', () => {
        const route = vi.fn((name: string) => `/${name}`);
        vi.stubGlobal('route', route);

        const wrapper = mount(BetaTesterFaqs, {
            global: {
                stubs: {
                    DarkModeToggle: true,
                    BrandCopy: { props: ['text'], template: '<span>{{ text }}</span>' },
                    Link: {
                        props: ['href'],
                        template: '<a :href="href"><slot /></a>',
                    },
                },
                mocks: {
                    route,
                },
            },
        });

        expect(wrapper.text()).toContain('Who is it for?');
        expect(wrapper.text()).toContain('People who want:');
        expect(wrapper.text()).toContain('A clear plan on the floor');
        expect(wrapper.text()).toContain('Overload built into the flow');
        expect(wrapper.text()).toContain('A gym companion, not a coach');
        expect(wrapper.text()).not.toContain('What’s more important is what');
        expect(wrapper.text()).not.toContain('serious about their lifting');

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/about');
        expect(route).toHaveBeenCalledWith('about');
    });
});
