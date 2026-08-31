import { vi } from 'vitest';
import { reactive } from 'vue';

const inertia = vi.hoisted(() => {
    const inertiaFormPut = vi.fn();
    const inertiaFormPost = vi.fn();
    const pageProps = {
        flash: { success: null as string | null, error: null as string | null },
        errors: {} as Record<string, string>,
    };
    const routerMocks = {
        delete: vi.fn(),
        post: vi.fn(),
        visit: vi.fn(),
        on: vi.fn(() => vi.fn()),
    };
    let lastTransformed: unknown = null;

    function createForm(initial: object) {
        return reactive({
            ...initial,
            processing: false,
            errors: {},
            recentlySuccessful: false,
            clearErrors: vi.fn(),
            transform(fn: (data: object) => unknown) {
                // Keep put/post(url, options) like Inertia; stash payload for assertions.
                lastTransformed = fn({ ...(this as object) });
                return { put: inertiaFormPut, post: inertiaFormPost };
            },
            put: inertiaFormPut,
            post: inertiaFormPost,
            reset: vi.fn(),
        });
    }

    return {
        inertiaFormPut,
        inertiaFormPost,
        pageProps,
        routerMocks,
        createForm,
        get lastTransformed() {
            return lastTransformed;
        },
        clearLastTransformed() {
            lastTransformed = null;
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    useForm: (keyOrInitial: string | object, maybeInitial?: object) =>
        inertia.createForm(typeof keyOrInitial === 'string' ? (maybeInitial ?? {}) : keyOrInitial),
    useHttp: (initial: object) => inertia.createForm(initial),
    usePage: () => ({ props: inertia.pageProps }),
    router: inertia.routerMocks,
    Head: {
        name: 'Head',
        props: ['title'],
        setup: () => () => null,
    },
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

(globalThis as typeof globalThis & { __inertiaMocks: typeof inertia }).__inertiaMocks = inertia;

vi.stubGlobal(
    'route',
    vi.fn((name: string, _params?: unknown) => `/${String(name)}`),
);
