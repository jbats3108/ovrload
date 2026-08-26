import {
    accountNavItems,
    isAccountActive,
    isPathActive,
    isPreferencesActive,
    mobileNavItems,
    preferencesNavItems,
    primaryNavItems,
} from '@/shared/lib/appNav';
import { describe, expect, it } from 'vitest';

const route = (name: string) => `/${name}`;

describe('isPathActive', () => {
    it('matches exact paths and nested routes', () => {
        expect(isPathActive('/dashboard', '/dashboard')).toBe(true);
        expect(isPathActive('/dashboard/stats', '/dashboard')).toBe(true);
        expect(isPathActive('/history', '/dashboard')).toBe(false);
    });
});

describe('isPreferencesActive', () => {
    it('matches training and appearance preference paths', () => {
        expect(isPreferencesActive('/settings/training')).toBe(true);
        expect(isPreferencesActive('/settings/training/plates')).toBe(true);
        expect(isPreferencesActive('/settings/appearance')).toBe(true);
        expect(isPreferencesActive('/settings/profile')).toBe(false);
    });
});

describe('isAccountActive', () => {
    it('matches account paths without matching preferences', () => {
        expect(isAccountActive('/settings/profile')).toBe(true);
        expect(isAccountActive('/settings/profile/password')).toBe(true);
        expect(isAccountActive('/settings/appearance')).toBe(false);
    });
});

describe('primaryNavItems', () => {
    it('includes admin link when user is admin', () => {
        const links = primaryNavItems(route, { isAdmin: true });
        expect(links.map((link) => link.label)).toEqual(['Dashboard', 'History', 'Admin']);
    });

    it('omits admin link for non-admin users', () => {
        const links = primaryNavItems(route, { isAdmin: false });
        expect(links.map((link) => link.label)).toEqual(['Dashboard', 'History']);
    });
});

describe('preferencesNavItems', () => {
    it('returns the preference links', () => {
        const links = preferencesNavItems(route);
        expect(links.map((link) => link.label)).toEqual(['Training', 'Appearance']);
    });
});

describe('accountNavItems', () => {
    it('returns the account links', () => {
        const links = accountNavItems(route);
        expect(links.map((link) => link.label)).toEqual(['Profile']);
    });
});

describe('mobileNavItems', () => {
    it('places admin last for admins', () => {
        const links = mobileNavItems(route, { isAdmin: true });
        expect(links.map((link) => link.label)).toEqual(['Dashboard', 'History', 'Preferences', 'Account', 'Admin']);
    });

    it('returns four tabs for regular users', () => {
        const links = mobileNavItems(route, { isAdmin: false });
        expect(links.map((link) => link.label)).toEqual(['Dashboard', 'History', 'Preferences', 'Account']);
    });
});
