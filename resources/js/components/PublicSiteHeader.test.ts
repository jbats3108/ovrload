import PublicSiteHeader from '@/components/PublicSiteHeader.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

describe('PublicSiteHeader', () => {
    it('links to home, login, about, beta FAQs, and privacy', () => {
        const route = vi.fn((name: string) => `/${name}`);
        vi.stubGlobal('route', route);

        const wrapper = mount(PublicSiteHeader, {
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

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/home');
        expect(hrefs).toContain('/login');
        expect(hrefs).toContain('/about');
        expect(hrefs).toContain('/beta-tester-faqs');
        expect(hrefs).toContain('/privacy');
        expect(wrapper.text()).toContain('Log in');
        expect(wrapper.text()).toContain('About');
        expect(wrapper.text()).toContain('Beta testers');
        expect(wrapper.text()).toContain('Privacy');
    });

    it('omits the current page from the nav', () => {
        const route = vi.fn((name: string) => `/${name}`);
        vi.stubGlobal('route', route);

        const wrapper = mount(PublicSiteHeader, {
            props: { current: 'privacy' },
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

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/home');
        expect(hrefs).toContain('/login');
        expect(hrefs).toContain('/about');
        expect(hrefs).toContain('/beta-tester-faqs');
        expect(hrefs).not.toContain('/privacy');
        expect(wrapper.text()).not.toContain('Privacy');
    });

    it('omits log in on the home page', () => {
        const route = vi.fn((name: string) => `/${name}`);
        vi.stubGlobal('route', route);

        const wrapper = mount(PublicSiteHeader, {
            props: { current: 'home' },
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

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).not.toContain('/login');
        expect(hrefs).toContain('/about');
        expect(hrefs).toContain('/beta-tester-faqs');
        expect(hrefs).toContain('/privacy');
        expect(wrapper.text()).not.toContain('Log in');
    });
});
