import AreaChart from '@/Components/Charts/AreaChart';
import ChartCard from '@/Components/Dashboard/ChartCard';
import ComingSoon from '@/Components/Dashboard/ComingSoon';
import StatBadge from '@/Components/Dashboard/StatBadge';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';
import SaimsErrorState from '@/Components/Saims/SaimsErrorState';
import { ButtonLink, Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import { formatNumber, formatPhp, formatPhpCompact } from '@/Utils/format';

/**
 * Revenue in the selected date range.
 *  - source "saims": "Invoiced (incl. VAT)", the sum of total_sales of the non-cancelled
 *    invoices in SAIMS (not "closed won");
 *  - source "leads": the value of won leads (users without access to SAIMS invoices).
 * The target and its progress bar only exist when CRM_REVENUE_TARGET is set.
 * `data.error` is a SAIMS failure that only affects this card.
 */
export default function RevenueGoal({ data }) {
    const { can } = usePermission();
    const canAddLead = can('leads.create') && can('leads.view');
    const canAddTask = can('tasks.create');

    const failed = data && data.error;
    const hasTarget = data && !failed && data.target;
    const progress = hasTarget ? Math.min(Math.round((data.amount / data.target) * 100), 100) : 0;

    return (
        <ChartCard
            title="Revenue Goal"
            caption={data && !failed ? `${data.label} · ${data.source === 'saims' ? 'from SAIMS' : 'from your leads'}` : 'Revenue in the selected range'}
            action={data && !failed && data.change_percent !== null ? <StatBadge value={data.change_percent} /> : null}
        >
            {data === null ? (
                <Skeleton className="h-44 w-full" />
            ) : failed ? (
                <SaimsErrorState error={data.error} compact />
            ) : (
                <>
                    <p className="text-[1.65rem] font-bold leading-tight tracking-tight text-gray-900">{formatPhp(data.amount)}</p>

                    {data.source === 'saims' && (
                        <p className="mt-1 text-xs text-gray-500">
                            {formatNumber(data.invoices)} {data.invoices === 1 ? 'invoice' : 'invoices'}, cancelled ones left out
                        </p>
                    )}

                    {hasTarget && (
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
                    )}

                    <div className="mt-4">
                        {data.series.every((point) => point.value === 0) ? (
                            <EmptyState>{data.source === 'saims' ? 'No invoices in this range' : 'No won leads in this range'}</EmptyState>
                        ) : (
                            <AreaChart
                                data={data.series}
                                title={`${data.label} per month`}
                                formatValue={formatPhp}
                            />
                        )}
                    </div>

                    {data.partial && (
                        <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Partial: the range has more invoices than can be read at once, so this total is incomplete.
                            Choose a shorter range for an exact figure.
                        </p>
                    )}
                    {data.note && <p className="mt-3 text-xs text-gray-500">{data.note}</p>}
                </>
            )}

            {(canAddLead || canAddTask) && (
                <div className="mt-5 flex flex-wrap gap-3">
                    {canAddLead && (
                        <ButtonLink href={route('leads.create')} variant="ghost">
                            <Icon path={icons.plus} className="h-4 w-4" />
                            Add Lead
                        </ButtonLink>
                    )}
                    {canAddTask && (
                        <ComingSoon tooltip="Tasks module coming soon" variant="ghost">
                            <Icon path={icons.check} className="h-4 w-4" />
                            Task
                        </ComingSoon>
                    )}
                </div>
            )}
        </ChartCard>
    );
}
