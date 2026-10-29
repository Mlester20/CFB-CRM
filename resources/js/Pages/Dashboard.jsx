import Card from '@/Components/Dashboard/Card';
import DateRangePicker from '@/Components/Dashboard/DateRangePicker';
import { ButtonLink, Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Deferred, Head } from '@inertiajs/react';
import AiInsights from './Dashboard/Partials/AiInsights';
import Conversion from './Dashboard/Partials/Conversion';
import Engagement from './Dashboard/Partials/Engagement';
import FollowUps from './Dashboard/Partials/FollowUps';
import LeadActivity from './Dashboard/Partials/LeadActivity';
import LeadSources from './Dashboard/Partials/LeadSources';
import PipelineGoal from './Dashboard/Partials/PipelineGoal';
import PipelineStages from './Dashboard/Partials/PipelineStages';
import RevenueGoal from './Dashboard/Partials/RevenueGoal';
import TopContacts from './Dashboard/Partials/TopContacts';
import TopOpenDeals from './Dashboard/Partials/TopOpenDeals';
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

    // Invoiced revenue comes from SAIMS and arrives after the page has opened (a deferred
    // prop), so the rest of the dashboard never waits for SAIMS. Without saims.invoices the
    // revenue widgets are lead-based and arrive with the page.
    const weekly =
        props.revenueSource === 'saims' ? (
            <Deferred key="weekly" data="weeklyRevenue" fallback={<WeeklyRevenue data={null} />}>
                <WeeklyRevenue data={props.weeklyRevenue} />
            </Deferred>
        ) : (
            has(props.weeklyRevenue) && <WeeklyRevenue key="weekly" data={props.weeklyRevenue} />
        );

    const goal =
        props.revenueSource === 'saims' ? (
            <Deferred key="revenueGoal" data="revenueGoal" fallback={<RevenueGoal data={null} />}>
                <RevenueGoal data={props.revenueGoal} />
            </Deferred>
        ) : (
            has(props.revenueGoal) && <RevenueGoal key="revenueGoal" data={props.revenueGoal} />
        );

    const left = [
        has(props.pipeline) && <PipelineGoal key="pipeline" data={props.pipeline} />,
        weekly,
        has(props.conversion) && <Conversion key="conversion" data={props.conversion} />,
        has(props.followUps) && <FollowUps key="followUps" data={props.followUps} />,
        has(props.topContacts) && <TopContacts key="topContacts" data={props.topContacts} />,
    ].filter(Boolean);

    const center = [
        has(props.engagement) && <Engagement key="engagement" data={props.engagement} />,
        has(props.activity) && <LeadActivity key="activity" data={props.activity} />,
        has(props.pipelineStages) && <PipelineStages key="pipelineStages" data={props.pipelineStages} />,
    ].filter(Boolean);

    const right = [
        goal,
        has(props.insights) && <AiInsights key="insights" data={props.insights} />,
        has(props.sources) && <LeadSources key="sources" data={props.sources} />,
        has(props.topOpenDeals) && <TopOpenDeals key="topOpenDeals" data={props.topOpenDeals} />,
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
                        <DateRangePicker period={period} />

                        {can('leads.create') && can('leads.view') && (
                            <ButtonLink href={route('leads.create')}>
                                <Icon path={icons.plus} className="h-4 w-4" />
                                Add New Lead
                            </ButtonLink>
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
