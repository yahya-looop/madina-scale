import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';

export function useAuthorization() {
    const page = usePage();

    const auth = computed<Auth>(() => page.props.auth);

    const isDeveloper = computed<boolean>(() => auth.value.is_developer);

    const roles = computed(() => auth.value.roles);

    const permissions = computed<string[]>(() => auth.value.permissions);

    const can = (permission: string): boolean => {
        const slug = permission.trim();

        if (slug === '') {
            return false;
        }

        if (isDeveloper.value) {
            return true;
        }

        return permissions.value.includes(slug);
    };

    const canAll = (permissionSlugs: string[]): boolean => {
        if (permissionSlugs.length === 0) {
            return true;
        }

        return permissionSlugs.every((permission) => can(permission));
    };

    const canAny = (permissionSlugs: string[]): boolean => {
        if (permissionSlugs.length === 0) {
            return false;
        }

        return permissionSlugs.some((permission) => can(permission));
    };

    const hasRole = (roleSlug: string): boolean => {
        const slug = roleSlug.trim();

        if (slug === '') {
            return false;
        }

        return roles.value.some((role) => role.slug === slug);
    };

    const hasAnyRole = (roleSlugs: string[]): boolean => {
        if (roleSlugs.length === 0) {
            return false;
        }

        return roleSlugs.some((role) => hasRole(role));
    };

    return {
        auth,
        isDeveloper,
        roles,
        permissions,
        can,
        canAll,
        canAny,
        hasRole,
        hasAnyRole,
    };
}
