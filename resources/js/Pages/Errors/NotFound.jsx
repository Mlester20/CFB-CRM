import { Button, ButtonLink } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

function Content({ signedIn }) {
    const { can } = usePermission();

    return (
        <div className="mx-auto flex max-w-md flex-col items-center py-16 text-center">
            <p className="text-6xl font-bold text-primary-600">404</p>
            <h1 className="mt-4 text-2xl font-bold text-gray-900">Page not found</h1>
            <p className="mt-2 text-sm text-gray-600">
                The page you are looking for does not exist or is not available to you.
            </p>

            <div className="mt-8 flex flex-wrap justify-center gap-3">
                <Button variant="secondary" onClick={() => window.history.back()}>
                    Go back
                </Button>
                {signedIn && can('dashboard.view') ? (
                    <ButtonLink href={route('dashboard')}>Dashboard</ButtonLink>
                ) : (
                    <ButtonLink href={route('login')}>Sign in</ButtonLink>
                )}
            </div>
        </div>
    );
}

export default function NotFound() {
    const { auth } = usePage().props;

    if (auth?.user) {
        return (
            <AuthenticatedLayout>
                <Head title="Page not found" />
                <Content signedIn />
            </AuthenticatedLayout>
        );
    }

    return (
        <div className="min-h-screen bg-canvas">
            <Head title="Page not found" />
            <Content signedIn={false} />
        </div>
    );
}
