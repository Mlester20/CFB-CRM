import { Card } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const { role } = usePermission();

    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-xl font-semibold leading-tight text-gray-900">
                    Dashboard
                </h1>
            }
        >
            <Head title="Dashboard" />

            <Card className="p-6">
                <p className="text-gray-900">
                    Welcome back, <span className="font-semibold">{auth.user.name}</span>.
                </p>
                <p className="mt-1 text-sm text-gray-600">
                    You are signed in as {role}. The business modules will appear
                    here as they are built.
                </p>
            </Card>
        </AuthenticatedLayout>
    );
}
