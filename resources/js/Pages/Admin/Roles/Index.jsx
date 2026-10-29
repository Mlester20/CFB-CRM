import { Badge, ButtonLink, Card, Icon, icons, moduleLabel } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ roles }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-xl font-semibold leading-tight text-gray-900">Roles</h1>
                    <p className="mt-1 text-sm text-gray-600">
                        The four roles are fixed. You can change which permissions each role has.
                    </p>
                </div>
            }
        >
            <Head title="Roles" />

            <div className="grid gap-6 xl:grid-cols-2">
                {roles.map((role) => (
                    <Card key={role.id} className="flex flex-col p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-semibold text-gray-900">
                                    {role.name}
                                    {role.locked && (
                                        <Badge tone="amber">
                                            <Icon path={icons.lock} className="me-1 h-3 w-3" />
                                            Locked
                                        </Badge>
                                    )}
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    {role.users_count} {role.users_count === 1 ? 'user' : 'users'} ·{' '}
                                    {role.permissions_count} permissions
                                </p>
                            </div>
                            <ButtonLink
                                href={route('admin.roles.edit', role.id)}
                                variant="secondary"
                            >
                                {role.locked ? 'View' : 'Edit permissions'}
                            </ButtonLink>
                        </div>

                        <dl className="mt-5 space-y-3">
                            {Object.entries(role.granted).map(([module, actions]) => (
                                <div key={module} className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <dt className="w-24 shrink-0 text-sm font-medium text-gray-900">
                                        {moduleLabel(module)}
                                    </dt>
                                    <dd className="flex flex-wrap gap-1.5">
                                        {actions.map((action) => (
                                            <Badge key={action} tone="blue">
                                                {action}
                                            </Badge>
                                        ))}
                                    </dd>
                                </div>
                            ))}
                            {Object.keys(role.granted).length === 0 && (
                                <p className="text-sm text-gray-500">No permissions assigned.</p>
                            )}
                        </dl>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
