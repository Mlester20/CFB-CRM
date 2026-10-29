import { Badge, Button, ButtonLink, Card, Icon, icons, inputClass } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const roleTone = {
    Admin: 'blue',
    'Sales Manager': 'amber',
    'Sales Rep': 'green',
    Viewer: 'gray',
};

function pageLabel(label) {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}

export default function Index({ users, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit = (e) => {
        e.preventDefault();

        router.get(
            route('admin.users.index'),
            search ? { search } : {},
            { preserveState: true, replace: true },
        );
    };

    const toggleActive = (user) => {
        const action = user.is_active ? 'Deactivate' : 'Reactivate';

        if (window.confirm(`${action} ${user.name}?`)) {
            router.patch(route('admin.users.toggle-active', user.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold leading-tight text-gray-900">Users</h1>
                    <ButtonLink href={route('admin.users.create')}>
                        <Icon path={icons.plus} className="h-4 w-4" />
                        New user
                    </ButtonLink>
                </div>
            }
        >
            <Head title="Users" />

            <Card>
                <form
                    onSubmit={submit}
                    role="search"
                    className="flex flex-wrap items-center gap-3 border-b border-gray-200 p-4"
                >
                    <div className="relative w-full sm:max-w-sm">
                        <label htmlFor="search" className="sr-only">
                            Search users
                        </label>
                        <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                            <Icon path={icons.search} />
                        </span>
                        <input
                            id="search"
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or email"
                            className={`${inputClass()} ps-10`}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                    {filters.search && (
                        <Link
                            href={route('admin.users.index')}
                            className="text-sm font-medium text-primary-700 hover:underline"
                        >
                            Clear
                        </Link>
                    )}
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50 text-start text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" className="px-4 py-3 text-start">User</th>
                                <th scope="col" className="px-4 py-3 text-start">Role</th>
                                <th scope="col" className="px-4 py-3 text-start">Status</th>
                                <th scope="col" className="px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-4 py-10 text-center text-gray-500">
                                        No users found.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">
                                            {user.name}
                                            {user.is_self && (
                                                <span className="ms-2 text-xs font-normal text-gray-500">(you)</span>
                                            )}
                                        </p>
                                        <p className="text-gray-500">{user.email}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.role ? (
                                            <Badge tone={roleTone[user.role] ?? 'gray'}>{user.role}</Badge>
                                        ) : (
                                            <Badge tone="red">No role</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge tone={user.is_active ? 'green' : 'red'}>
                                            {user.is_active ? 'Active' : 'Deactivated'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.users.edit', user.id)}
                                                className="rounded-md px-2 py-1 font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-600"
                                            >
                                                Edit
                                            </Link>
                                            {!user.is_self && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleActive(user)}
                                                    className={`rounded-md px-2 py-1 font-medium focus-visible:outline-2 focus-visible:outline-primary-600 ${
                                                        user.is_active
                                                            ? 'text-red-700 hover:bg-red-50'
                                                            : 'text-green-700 hover:bg-green-50'
                                                    }`}
                                                >
                                                    {user.is_active ? 'Deactivate' : 'Reactivate'}
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <nav
                        className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-4"
                        aria-label="Pagination"
                    >
                        <p className="text-sm text-gray-600">
                            Showing {users.from}–{users.to} of {users.total}
                        </p>
                        <div className="flex flex-wrap gap-1">
                            {users.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        aria-current={link.active ? 'page' : undefined}
                                        className={`rounded-md px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-primary-600 font-semibold text-white'
                                                : 'text-gray-700 hover:bg-gray-100'
                                        }`}
                                    >
                                        {pageLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span key={index} className="rounded-md px-3 py-1.5 text-sm text-gray-400">
                                        {pageLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </nav>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
