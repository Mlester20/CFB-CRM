import Avatar from '@/Components/Dashboard/Avatar';
import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';
import StatusPill from '@/Components/Dashboard/StatusPill';
import { formatDate, formatPhp, formatTime } from '@/Utils/format';

/**
 * The table adapts to the width of its card (container queries), not the
 * screen: narrow cards put the status under the value and the date under the
 * company; wider cards get separate Date and Status columns.
 */
export default function LeadActivity({ data }) {
    // The deal value only arrives for users who may see revenue.
    const showValue = data?.some((row) => row.value !== undefined);

    return (
        <ChartCard title="Lead Activity" caption="Most recent leads">
            {data === null ? (
                <div className="space-y-3" role="status" aria-busy="true">
                    <span className="sr-only">Loading…</span>
                    {[0, 1, 2, 3].map((i) => (
                        <Skeleton key={i} className="h-10 w-full" />
                    ))}
                </div>
            ) : data.length === 0 ? (
                <EmptyState>No leads yet</EmptyState>
            ) : (
                <div className="@container -mx-2">
                    <table className="w-full table-fixed text-sm">
                        <caption className="sr-only">Most recent lead activity</caption>
                        <thead>
                            <tr className="text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                <th scope="col" className="px-2 pb-2 text-start font-semibold">Lead</th>
                                <th scope="col" className="hidden w-28 px-2 pb-2 text-start font-semibold @lg:table-cell">
                                    Date
                                </th>
                                <th
                                    scope="col"
                                    className={`w-28 px-2 pb-2 text-start font-semibold ${showValue ? 'hidden @md:table-cell' : ''}`}
                                >
                                    Status
                                </th>
                                {showValue && (
                                    <th scope="col" className="w-36 px-2 pb-2 text-end font-semibold">Value</th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.map((row) => (
                                <tr key={row.id}>
                                    <td className="overflow-hidden px-2 py-3">
                                        <div className="flex items-center gap-3">
                                            <Avatar name={row.name} />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-semibold text-gray-900" title={row.name}>{row.name}</p>
                                                <p className="truncate text-xs text-gray-500" title={row.company}>{row.company}</p>
                                                <p className="text-xs text-gray-500 @lg:hidden">
                                                    {formatDate(row.date)}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="hidden whitespace-nowrap px-2 py-3 @lg:table-cell">
                                        <p className="text-gray-900">{formatDate(row.date)}</p>
                                        <p className="text-xs text-gray-500">{formatTime(row.time)}</p>
                                    </td>
                                    <td className={`px-2 py-3 ${showValue ? 'hidden @md:table-cell' : ''}`}>
                                        <StatusPill status={row.status} />
                                    </td>
                                    {showValue && (
                                        <td className="whitespace-nowrap px-2 py-3 text-end">
                                            <p className="font-semibold text-gray-900">{formatPhp(row.value)}</p>
                                            <div className="mt-1 @md:hidden">
                                                <StatusPill status={row.status} />
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </ChartCard>
    );
}
