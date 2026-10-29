import { Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const appName = 'CRM';

const navigation = [
    {
        section: null,
        items: [
            {
                label: 'Dashboard',
                icon: icons.home,
                href: () => route('dashboard'),
                active: () => route().current('dashboard'),
                permission: 'dashboard.view',
            },
        ],
    },
    {
        section: 'Administration',
        items: [
            {
                label: 'Users',
                icon: icons.users,
                href: () => route('admin.users.index'),
                active: () => route().current('admin.users.*'),
                permission: 'users.manage',
            },
            {
                label: 'Roles',
                icon: icons.shield,
                href: () => route('admin.roles.index'),
                active: () => route().current('admin.roles.*'),
                permission: 'roles.manage',
            },
        ],
    },
];

function SidebarContent({ onNavigate }) {
    const { auth } = usePage().props;
    const { can, role } = usePermission();

    const sections = navigation
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => can(item.permission)),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <div className="flex h-full flex-col bg-linear-to-b from-primary-950 via-primary-900 to-primary-800 text-white">
            <div className="flex items-center gap-3 px-6 py-5">
                <span
                    className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/30"
                    aria-hidden="true"
                >
                    <Icon path={icons.logo} className="h-[18px] w-[18px]" />
                </span>
                <span className="text-lg font-semibold">{appName}</span>
            </div>

            <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Main">
                {sections.map((group) => (
                    <div key={group.section ?? 'main'}>
                        {group.section && (
                            <p className="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-primary-200/70">
                                {group.section}
                            </p>
                        )}
                        <ul className="space-y-1">
                            {group.items.map((item) => {
                                const active = item.active();

                                return (
                                    <li key={item.label}>
                                        <Link
                                            href={item.href()}
                                            onClick={onNavigate}
                                            aria-current={active ? 'page' : undefined}
                                            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-white ${
                                                active
                                                    ? 'bg-white/15 text-white'
                                                    : 'text-primary-100 hover:bg-white/10 hover:text-white'
                                            }`}
                                        >
                                            <Icon path={item.icon} />
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-white/10 p-4">
                <div className="px-2 pb-3">
                    <p className="truncate text-sm font-semibold">{auth.user.name}</p>
                    <p className="truncate text-xs text-primary-200">
                        {role ?? 'No role assigned'}
                    </p>
                </div>
                <div className="space-y-1">
                    <Link
                        href={route('profile.edit')}
                        onClick={onNavigate}
                        className="block rounded-lg px-3 py-2 text-sm text-primary-100 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                    >
                        Profile
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="block w-full rounded-lg px-3 py-2 text-start text-sm text-primary-100 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </div>
    );
}

function FlashMessages() {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className="space-y-3 px-4 pt-6 sm:px-6 lg:px-8">
            {flash.success && (
                <div
                    role="status"
                    className="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 ring-1 ring-green-200"
                >
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div
                    role="alert"
                    className="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700 ring-1 ring-red-200"
                >
                    {flash.error}
                </div>
            )}
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 start-0 z-30 hidden w-64 lg:block">
                <SidebarContent />
            </aside>

            {/* Mobile top bar and off-canvas sidebar */}
            <div className="sticky top-0 z-20 flex items-center gap-3 bg-primary-900 px-4 py-3 text-white lg:hidden">
                <button
                    type="button"
                    onClick={() => setMenuOpen(true)}
                    aria-label="Open menu"
                    aria-expanded={menuOpen}
                    className="rounded-md p-1.5 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white"
                >
                    <Icon path={icons.menu} className="h-6 w-6" />
                </button>
                <span className="font-semibold">{appName}</span>
            </div>

            {menuOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
                    <div
                        className="absolute inset-0 bg-gray-900/50"
                        onClick={() => setMenuOpen(false)}
                        aria-hidden="true"
                    />
                    <aside className="relative h-full w-64 max-w-[80%]">
                        <button
                            type="button"
                            onClick={() => setMenuOpen(false)}
                            aria-label="Close menu"
                            className="absolute end-2 top-3 z-10 rounded-md p-1.5 text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white"
                        >
                            <Icon path={icons.close} className="h-5 w-5" />
                        </button>
                        <SidebarContent onNavigate={() => setMenuOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:ps-64">
                {header && (
                    <header className="border-b border-gray-200 bg-white">
                        <div className="px-4 py-5 sm:px-6 lg:px-8">{header}</div>
                    </header>
                )}

                <FlashMessages />

                <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}
