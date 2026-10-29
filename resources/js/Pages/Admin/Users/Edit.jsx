import { Badge, Button, ButtonLink, Card, Field, inputClass } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ roles, managedUser }) {
    const details = useForm({
        name: managedUser.name,
        email: managedUser.email,
        role: managedUser.role ?? roles[roles.length - 1],
    });

    const password = useForm({
        password: '',
        password_confirmation: '',
    });

    const saveDetails = (e) => {
        e.preventDefault();
        details.put(route('admin.users.update', managedUser.id));
    };

    const resetPassword = (e) => {
        e.preventDefault();

        password.put(route('admin.users.reset-password', managedUser.id), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    const toggleActive = () => {
        const action = managedUser.is_active ? 'Deactivate' : 'Reactivate';

        if (window.confirm(`${action} ${managedUser.name}?`)) {
            router.patch(route('admin.users.toggle-active', managedUser.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-xl font-semibold leading-tight text-gray-900">
                    Edit {managedUser.name}
                </h1>
            }
        >
            <Head title={`Edit ${managedUser.name}`} />

            <div className="max-w-2xl space-y-6">
                <Card className="p-6">
                    <h2 className="text-base font-semibold text-gray-900">Details</h2>
                    <form onSubmit={saveDetails} className="mt-5 space-y-5" noValidate>
                        <Field label="Name" htmlFor="name" error={details.errors.name}>
                            <input
                                id="name"
                                value={details.data.name}
                                onChange={(e) => details.setData('name', e.target.value)}
                                required
                                className={inputClass(details.errors.name)}
                            />
                        </Field>

                        <Field label="Email" htmlFor="email" error={details.errors.email}>
                            <input
                                id="email"
                                type="email"
                                value={details.data.email}
                                onChange={(e) => details.setData('email', e.target.value)}
                                required
                                className={inputClass(details.errors.email)}
                            />
                        </Field>

                        <Field
                            label="Role"
                            htmlFor="role"
                            error={details.errors.role}
                            hint="Each user has exactly one role."
                        >
                            <select
                                id="role"
                                value={details.data.role}
                                onChange={(e) => details.setData('role', e.target.value)}
                                className={inputClass(details.errors.role)}
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <div className="flex items-center gap-3 pt-2">
                            <Button type="submit" disabled={details.processing}>
                                {details.processing ? 'Saving…' : 'Save changes'}
                            </Button>
                            <ButtonLink href={route('admin.users.index')} variant="secondary">
                                Back
                            </ButtonLink>
                        </div>
                    </form>
                </Card>

                <Card className="p-6">
                    <h2 className="text-base font-semibold text-gray-900">Reset password</h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Set a new password for this user and share it with them securely.
                    </p>
                    <form onSubmit={resetPassword} className="mt-5 space-y-5" noValidate>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="New password" htmlFor="new_password" error={password.errors.password}>
                                <input
                                    id="new_password"
                                    type="password"
                                    value={password.data.password}
                                    onChange={(e) => password.setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    className={inputClass(password.errors.password)}
                                />
                            </Field>
                            <Field label="Confirm new password" htmlFor="new_password_confirmation">
                                <input
                                    id="new_password_confirmation"
                                    type="password"
                                    value={password.data.password_confirmation}
                                    onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                    className={inputClass()}
                                />
                            </Field>
                        </div>
                        <Button type="submit" variant="secondary" disabled={password.processing}>
                            Reset password
                        </Button>
                    </form>
                </Card>

                <Card className="p-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="flex items-center gap-2 text-base font-semibold text-gray-900">
                                Account status
                                <Badge tone={managedUser.is_active ? 'green' : 'red'}>
                                    {managedUser.is_active ? 'Active' : 'Deactivated'}
                                </Badge>
                            </h2>
                            <p className="mt-1 text-sm text-gray-600">
                                {managedUser.is_self
                                    ? 'You cannot deactivate your own account.'
                                    : 'A deactivated user cannot sign in and is signed out on their next request.'}
                            </p>
                        </div>
                        {!managedUser.is_self && (
                            <Button variant="danger" onClick={toggleActive}>
                                {managedUser.is_active ? 'Deactivate user' : 'Reactivate user'}
                            </Button>
                        )}
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
