import AreaChart from '@/Components/Charts/AreaChart';
import ChartCard from '@/Components/Dashboard/ChartCard';
import ComingSoon from '@/Components/Dashboard/ComingSoon';
import StatBadge from '@/Components/Dashboard/StatBadge';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';
import { Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import { formatPhp, formatPhpCompact } from '@/Utils/format';

export default function RevenueGoal({ data }) {
    const { can } = usePermission();
    const canAddLead = can('leads.create');
    const canAddTask = can('tasks.create');

    const progress = data ? Math.min(Math.round((data.closed_won / data.target) * 100), 100) : 0;

    return (
        <ChartCard
            title="Revenue Goal"
            caption="Closed-won this year"
            action={data ? <StatBadge value={data.change_percent} /> : null}
        >
            {data === null ? (
                <Skeleton className="h-44 w-full" />
            ) : (
                <>
                    <p className="text-[1.65rem] font-bold leading-tight tracking-tight text-gray-900">
                        {formatPhp(data.closed_won)}
                    </p>

                    <div className="mt-3">
                        <div className="flex justify-between text-xs text-gray-500">
                            <span>{progress}% of goal</span>
                            <span>{formatPhpCompact(data.target)}</span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label="Revenue goal progress"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={progress}
                            className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                className="h-full rounded-full bg-linear-to-r from-sky-400 to-primary-600"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>

                    <div className="mt-4">
                        {data.series.length === 0 ? (
                            <EmptyState>No closed deals yet</EmptyState>
                        ) : (
                            <AreaChart
                                data={data.series}
                                title="Closed-won revenue per month"
                                formatValue={formatPhp}
                            />
                        )}
                    </div>

                    {(canAddLead || canAddTask) && (
                        <div className="mt-5 flex flex-wrap gap-3">
                            {canAddLead && (
                                <ComingSoon tooltip="Leads module coming soon" variant="ghost">
                                    <Icon path={icons.plus} className="h-4 w-4" />
                                    Add Lead
                                </ComingSoon>
                            )}
                            {canAddTask && (
                                <ComingSoon tooltip="Tasks module coming soon" variant="ghost">
                                    <Icon path={icons.check} className="h-4 w-4" />
                                    Task
                                </ComingSoon>
                            )}
                        </div>
                    )}
                </>
            )}
        </ChartCard>
    );
}
