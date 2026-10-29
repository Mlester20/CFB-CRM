import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, WidgetSkeleton } from '@/Components/Dashboard/States';
import StatusPill from '@/Components/Dashboard/StatusPill';
import { Icon, icons } from '@/Components/Ui';
import { formatPhp } from '@/Utils/format';

/**
 * Narrow cards stack the value and stage under the company; wider cards put
 * them on the right (container queries, so it follows the card, not the screen).
 * The peso value only arrives for users who may see revenue.
 */
export default function TopOpenDeals({ data }) {
    return (
        <ChartCard title="Top Open Deals" caption="Your biggest deals in play">
            {data === null ? (
                <WidgetSkeleton lines={4} />
            ) : data.length === 0 ? (
                <EmptyState>No open deals yet</EmptyState>
            ) : (
                <ol className="@container space-y-4">
                    {data.map((deal) => (
                        <li key={deal.id} className="flex items-start gap-3">
                            <span
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-xs font-bold text-sky-700"
                                aria-label={`Rank ${deal.rank}`}
                            >
                                {deal.rank}
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-gray-900">{deal.name}</p>
                                <p className="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                                    <Icon path={icons.building} className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">{deal.company}</span>
                                </p>

                                <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 @xs:hidden">
                                    {deal.value !== undefined && (
                                        <p className="whitespace-nowrap text-sm font-semibold text-gray-900">{formatPhp(deal.value)}</p>
                                    )}
                                    <StatusPill status={deal.stage} />
                                </div>
                            </div>

                            <div className="hidden shrink-0 flex-col items-end gap-1 @xs:flex">
                                {deal.value !== undefined && (
                                    <p className="whitespace-nowrap text-sm font-semibold text-gray-900">{formatPhp(deal.value)}</p>
                                )}
                                <StatusPill status={deal.stage} />
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </ChartCard>
    );
}
