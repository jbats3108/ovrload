export type ZiggyRouteFn = (name: string, params?: Record<string, unknown>, absolute?: boolean) => string;

export type AppNavLink = {
    href: string;
    label: string;
    match: string;
};

export function isPathActive(path: string, match: string): boolean {
    return path === match || path.startsWith(`${match}/`);
}

export function isPreferencesActive(path: string): boolean {
    return isPathActive(path, '/settings/training') || isPathActive(path, '/settings/appearance');
}

export function isAccountActive(path: string): boolean {
    return isPathActive(path, '/settings/profile');
}

export function primaryNavItems(route: ZiggyRouteFn, { isAdmin }: { isAdmin: boolean }): AppNavLink[] {
    const links: AppNavLink[] = [
        { href: route('dashboard'), label: 'Dashboard', match: '/dashboard' },
        { href: route('history.index'), label: 'History', match: '/history' },
    ];

    if (isAdmin) {
        links.push({ href: route('admin.index'), label: 'Admin', match: '/admin' });
    }

    return links;
}

export function preferencesNavItems(route: ZiggyRouteFn): AppNavLink[] {
    return [
        { href: route('training.edit'), label: 'Training', match: '/settings/training' },
        { href: route('appearance'), label: 'Appearance', match: '/settings/appearance' },
    ];
}

export function accountNavItems(route: ZiggyRouteFn): AppNavLink[] {
    return [{ href: route('profile.edit'), label: 'Profile', match: '/settings/profile' }];
}

export function mobileNavItems(route: ZiggyRouteFn, { isAdmin }: { isAdmin: boolean }): AppNavLink[] {
    const items: AppNavLink[] = [
        ...primaryNavItems(route, { isAdmin: false }),
        { href: route('training.edit'), label: 'Preferences', match: '/settings/training' },
        { href: route('profile.edit'), label: 'Account', match: '/settings/profile' },
    ];

    if (isAdmin) {
        items.push({ href: route('admin.index'), label: 'Admin', match: '/admin' });
    }

    return items;
}
