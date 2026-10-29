import Avatar from '@/Components/Dashboard/Avatar';
import Dropdown from '@/Components/Dropdown';
import { Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const appName = 'CFB CRM';

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
            {
                label: 'Leads',
                icon: icons.leads,
                href: () => route('leads.index'),
                active: () => route().current('leads.*'),
                permission: 'leads.view',
            },
        ],
    },
    {
        section: 'SAIMS',
        items: [
            {
                label: 'Customers',
                icon: icons.users,
                href: () => route('saims.customers.index'),
                active: () => route().current('saims.customers.*'),
                permission: 'saims.customers',
            },
            {
                label: 'Products',
                icon: icons.cube,
                href: () => route('saims.products.index'),
                active: () => route().current('saims.products.*'),
                permission: 'saims.products',
            },
            {
                label: 'Sales Orders',
                icon: icons.orders,
                href: () => route('saims.orders.index'),
                active: () => route().current('saims.orders.*'),
                permission: 'saims.orders',
            },
            {
                label: 'Invoices',
                icon: icons.invoice,
                href: () => route('saims.invoices.index'),
                active: () => route().current('saims.invoices.*'),
                permission: 'saims.invoices',
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
            {
                label: 'SAIMS connection',
                icon: icons.link,
                href: () => route('admin.saims'),
                active: () => route().current('admin.saims'),
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

function TopBar({ onOpenMenu, menuOpen }) {
    const { auth } = usePage().props;
    const { role } = usePermission();

    return (
        <div className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200/70 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button
                type="button"
                onClick={onOpenMenu}
                aria-label="Open menu"
                aria-expanded={menuOpen}
                className="rounded-md p-1.5 text-gray-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-primary-600 lg:hidden"
            >
                <Icon path={icons.menu} className="h-6 w-6" />
            </button>
            <span className="font-semibold text-gray-900 lg:hidden">{appName}</span>

            {/* Display only for now: clearly inactive, not a broken control. */}
            <div
                aria-disabled="true"
                title="Search is coming soon"
                className="relative ms-2 hidden w-full max-w-sm cursor-not-allowed sm:block"
            >
                <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-300">
                    <Icon path={icons.search} />
                </span>
                <input
                    type="search"
                    disabled
                    aria-disabled="true"
                    aria-label="Search (coming soon)"
                    placeholder="Search (coming soon)"
                    className="block w-full cursor-not-allowed rounded-xl border-0 bg-slate-100 py-2 ps-10 pe-3 text-sm text-gray-400 placeholder-gray-400"
                />
            </div>

            <div className="ms-auto flex items-center gap-1.5">
                <button
                    type="button"
                    aria-disabled="true"
                    aria-label="Notifications (coming soon)"
                    title="Notifications are coming soon"
                    onClick={(event) => event.preventDefault()}
                    className="cursor-not-allowed rounded-full p-2 text-gray-300 focus-visible:outline-2 focus-visible:outline-primary-600"
                >
                    <Icon path={icons.bell} className="h-6 w-6" />
                </button>

                <Dropdown>
                    <Dropdown.Trigger>
                        <button
                            type="button"
                            aria-label={`Account menu for ${auth.user.name}`}
                            className="flex items-center gap-2 rounded-full py-1 ps-1 pe-2 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-primary-600"
                        >
                            <Avatar name={auth.user.name} size="sm" />
                            <span className="hidden text-start leading-tight sm:block">
                                <span className="block max-w-32 truncate text-sm font-semibold text-gray-900">
                                    {auth.user.name}
                                </span>
                                <span className="block text-xs text-gray-500">{role ?? 'No role'}</span>
                            </span>
                            <Icon path={icons.chevronDown} className="hidden h-4 w-4 text-gray-400 sm:block" />
                        </button>
                    </Dropdown.Trigger>
                    <Dropdown.Content>
                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                        <Dropdown.Link href={route('logout')} method="post" as="button">
                            Log out
                        </Dropdown.Link>
                    </Dropdown.Content>
                </Dropdown>
            </div>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="min-h-screen bg-canvas">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 start-0 z-30 hidden w-64 lg:block">
                <SidebarContent />
            </aside>

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
                <TopBar onOpenMenu={() => setMenuOpen(true)} menuOpen={menuOpen} />

                {header && (
                    <header className="border-b border-slate-200/70 bg-white">
                        <div className="px-4 py-5 sm:px-6 lg:px-8">{header}</div>
                    </header>
                )}

                <FlashMessages />

                <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}
