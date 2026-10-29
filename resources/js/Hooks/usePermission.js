import { usePage } from '@inertiajs/react';

/**
 * Reflects the backend's permissions in the UI (hide menu items and buttons).
 * The server still enforces every permission; this never replaces it.
 *
 *   const { can } = usePermission();
 *   can('users.manage')
 */
export default function usePermission() {
    const { permissions = [], role = null } = usePage().props.auth;

    const can = (permission) => permissions.includes(permission);
    const canAny = (...list) => list.some(can);

    return { can, canAny, role };
}
