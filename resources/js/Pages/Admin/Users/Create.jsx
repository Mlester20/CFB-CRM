import { Button, ButtonLink, Card, Field, inputClass } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ roles }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: roles[roles.length - 1],
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('admin.users.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold leading-tight text-gray-900">New user</h1>}
        >
            <Head title="New user" />

            <Card className="max-w-2xl p-6">
                <form onSubmit={submit} className="space-y-5" noValidate>
                    <Field label="Name" htmlFor="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="off"
                            required
                            className={inputClass(errors.name)}
                        />
                    </Field>

                    <Field label="Email" htmlFor="email" error={errors.email}>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                            required
                            className={inputClass(errors.email)}
                        />
                    </Field>

                    <Field
                        label="Role"
                        htmlFor="role"
                        error={errors.role}
                        hint="Each user has exactly one role."
                    >
                        <select
                            id="role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            className={inputClass(errors.role)}
                        >
                            {roles.map((role) => (
                                <option key={role} value={role}>
                                    {role}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Password" htmlFor="password" error={errors.password}>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                required
                                className={inputClass(errors.password)}
                            />
                        </Field>
                        <Field label="Confirm password" htmlFor="password_confirmation">
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                required
                                className={inputClass()}
                            />
                        </Field>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create user'}
                        </Button>
                        <ButtonLink href={route('admin.users.index')} variant="secondary">
                            Cancel
                        </ButtonLink>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
