import About from '@/pages/About.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

describe('About', () => {
    it('renders the three story sections and links to beta FAQs', () => {
        const route = vi.fn((name: string) => `/${name}`);
        vi.stubGlobal('route', route);

        const wrapper = mount(About, {
            global: {
                stubs: {
                    DarkModeToggle: true,
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

        expect(wrapper.text()).toContain('About me');
        expect(wrapper.text()).toContain('Why I built it');
        expect(wrapper.text()).toContain('Who is it for?');
        expect(wrapper.text()).toContain('People who want:');
        expect(wrapper.text()).toContain('A clear plan on the floor');
        expect(wrapper.text()).toContain('Overload built into the flow');
        expect(wrapper.text()).toContain('A gym companion, not a coach');

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/beta-tester-faqs');
        expect(route).toHaveBeenCalledWith('beta-tester-faqs');
    });
});
