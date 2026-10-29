import Card from '@/Components/Dashboard/Card';
import ComingSoon from '@/Components/Dashboard/ComingSoon';
import { Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import AiInsights from './Dashboard/Partials/AiInsights';
import Conversion from './Dashboard/Partials/Conversion';
import Engagement from './Dashboard/Partials/Engagement';
import FollowUps from './Dashboard/Partials/FollowUps';
import LeadActivity from './Dashboard/Partials/LeadActivity';
import LeadSources from './Dashboard/Partials/LeadSources';
import PipelineGoal from './Dashboard/Partials/PipelineGoal';
import RevenueGoal from './Dashboard/Partials/RevenueGoal';
import WeeklyRevenue from './Dashboard/Partials/WeeklyRevenue';

// Static class names (Tailwind cannot see dynamically built ones).
const gridByColumns = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_minmax(0,1fr)]',
};

/**
 * A widget shows only when its prop arrived: the backend sends the data of a
 * widget only to users who may see it. `null` means "still loading".
 */
const has = (value) => value !== undefined;

export default function Dashboard(props) {
    const { auth, period } = props;
    const { can } = usePermission();

    const firstName = auth.user.name.split(' ')[0];

    const left = [
        has(props.pipeline) && <PipelineGoal key="pipeline" data={props.pipeline} />,
        has(props.weeklyRevenue) && <WeeklyRevenue key="weekly" data={props.weeklyRevenue} />,
        has(props.conversion) && <Conversion key="conversion" data={props.conversion} />,
        has(props.followUps) && <FollowUps key="followUps" data={props.followUps} />,
    ].filter(Boolean);

    const center = [
        has(props.engagement) && <Engagement key="engagement" data={props.engagement} />,
        has(props.activity) && <LeadActivity key="activity" data={props.activity} />,
    ].filter(Boolean);

    const right = [
        has(props.revenueGoal) && <RevenueGoal key="revenueGoal" data={props.revenueGoal} />,
        has(props.insights) && <AiInsights key="insights" data={props.insights} />,
        has(props.sources) && <LeadSources key="sources" data={props.sources} />,
    ].filter(Boolean);

    // Columns left empty by permissions are dropped, so the others reflow.
    const columns = [
        { key: 'left', widgets: left, className: '' },
        { key: 'center', widgets: center, className: 'md:order-first md:col-span-2 xl:order-none xl:col-span-1' },
        { key: 'right', widgets: right, className: '' },
    ].filter((column) => column.widgets.length > 0);

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-[96rem]">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                            Welcome back, <span className="text-sky-600">{firstName}</span>
                        </h1>
                        <p className="mt-1 text-sm text-gray-600">Here's how your sales are doing.</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <span className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs ring-1 ring-slate-200">
                            <Icon path={icons.calendar} className="h-4 w-4 text-sky-600" />
                            <span className="sr-only">Period: </span>
                            {formatDate(period.from)} – {formatDate(period.to)}
                        </span>

                        {can('leads.create') && (
                            <ComingSoon tooltip="Leads module coming soon">
                                <Icon path={icons.plus} className="h-4 w-4" />
                                Add New Lead
                            </ComingSoon>
                        )}
                    </div>
                </div>

                {columns.length === 0 ? (
                    <Card className="mt-8 py-12 text-center">
                        <h2 className="text-base font-bold text-gray-900">Nothing to show yet</h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Your role does not include any dashboard widgets. Ask an administrator if you need access.
                        </p>
                    </Card>
                ) : (
                    <div className={`mt-8 grid items-start gap-6 ${gridByColumns[columns.length]}`}>
                        {columns.map((column) => (
                            <div key={column.key} className={`flex min-w-0 flex-col gap-6 ${column.className}`}>
                                {column.widgets}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
