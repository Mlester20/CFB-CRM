import { Button, ButtonLink } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Forbidden() {
    const { can } = usePermission();

    return (
        <AuthenticatedLayout>
            <Head title="Access denied" />

            <div className="mx-auto flex max-w-md flex-col items-center py-16 text-center">
                <p className="text-6xl font-bold text-primary-600">403</p>
                <h1 className="mt-4 text-2xl font-bold text-gray-900">
                    You don't have access to this page
                </h1>
                <p className="mt-2 text-sm text-gray-600">
                    Your role does not include the permission needed here. If you
                    think this is a mistake, ask an administrator.
                </p>

                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button variant="secondary" onClick={() => window.history.back()}>
                        Go back
                    </Button>
                    {can('dashboard.view') ? (
                        <ButtonLink href={route('dashboard')}>Dashboard</ButtonLink>
                    ) : (
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="inline-flex items-center justify-center rounded-lg bg-linear-to-r from-primary-600 to-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:from-primary-700 hover:to-primary-600"
                        >
                            Log out
                        </Link>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
