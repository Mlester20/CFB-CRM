import { Badge, Button, ButtonLink, Card, Icon, icons, moduleLabel } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ role, groups, granted }) {
    const { data, setData, put, processing, errors } = useForm({
        permissions: granted,
    });

    const selected = new Set(data.permissions);

    const toggle = (name) => {
        const next = new Set(selected);

        next.has(name) ? next.delete(name) : next.add(name);
        setData('permissions', [...next]);
    };

    const toggleModule = (group, checked) => {
        const next = new Set(selected);

        group.permissions.forEach((p) => (checked ? next.add(p.name) : next.delete(p.name)));
        setData('permissions', [...next]);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.roles.update', role.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h1 className="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-900">
                    {role.name}
                    {role.locked && (
                        <Badge tone="amber">
                            <Icon path={icons.lock} className="me-1 h-3 w-3" />
                            Locked
                        </Badge>
                    )}
                </h1>
            }
        >
            <Head title={`${role.name} permissions`} />

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                {role.locked && (
                    <div
                        role="note"
                        className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200"
                    >
                        The Admin role always has every permission and cannot be edited.
                    </div>
                )}

                {errors.permissions && (
                    <p role="alert" className="text-sm text-red-600">
                        {errors.permissions}
                    </p>
                )}

                <Card className="divide-y divide-gray-100">
                    {groups.map((group) => {
                        const allChecked = group.permissions.every((p) => selected.has(p.name));

                        return (
                            <fieldset key={group.module} className="p-5 sm:flex sm:gap-6">
                                <legend className="sr-only">{moduleLabel(group.module)} permissions</legend>

                                <div className="mb-3 sm:mb-0 sm:w-40 sm:shrink-0">
                                    <p className="text-sm font-semibold text-gray-900">
                                        {moduleLabel(group.module)}
                                    </p>
                                    {!role.locked && (
                                        <button
                                            type="button"
                                            onClick={() => toggleModule(group, !allChecked)}
                                            className="mt-1 rounded-sm text-xs font-medium text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            {allChecked ? 'Clear all' : 'Select all'}
                                        </button>
                                    )}
                                </div>

                                <div className="grid flex-1 grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                                    {group.permissions.map((permission) => {
                                        const id = `perm-${permission.name}`;

                                        return (
                                            <label
                                                key={permission.name}
                                                htmlFor={id}
                                                className="flex items-center gap-2 text-sm text-gray-700"
                                            >
                                                <input
                                                    id={id}
                                                    type="checkbox"
                                                    checked={role.locked || selected.has(permission.name)}
                                                    disabled={role.locked}
                                                    onChange={() => toggle(permission.name)}
                                                    className="h-4 w-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-600 disabled:opacity-60"
                                                />
                                                {permission.action}
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        );
                    })}
                </Card>

                <div className="flex items-center gap-3">
                    {!role.locked && (
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save permissions'}
                        </Button>
                    )}
                    <ButtonLink href={route('admin.roles.index')} variant="secondary">
                        {role.locked ? 'Back' : 'Cancel'}
                    </ButtonLink>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
