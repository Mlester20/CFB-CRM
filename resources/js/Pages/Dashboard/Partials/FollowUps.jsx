import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, WidgetSkeleton } from '@/Components/Dashboard/States';
import { formatDate } from '@/Utils/format';

export default function FollowUps({ data }) {
    return (
        <ChartCard title="Upcoming Follow-ups" caption="Tasks due soon">
            {data === null ? (
                <WidgetSkeleton lines={3} />
            ) : data.length === 0 ? (
                <EmptyState>No follow-ups due. You're all caught up.</EmptyState>
            ) : (
                <ul className="space-y-2.5">
                    {data.map((item) => (
                        <li
                            key={item.id}
                            className={`rounded-xl px-3 py-2.5 ${
                                item.overdue ? 'bg-rose-50 ring-1 ring-rose-200' : 'bg-slate-50'
                            }`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm font-semibold text-gray-900">{item.title}</p>
                                {item.overdue && (
                                    <span className="shrink-0 rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-bold text-white">
                                        Overdue
                                    </span>
                                )}
                            </div>
                            <p className="mt-0.5 text-xs text-gray-600">
                                {item.contact} ·{' '}
                                <span className={item.overdue ? 'font-semibold text-rose-700' : ''}>
                                    {formatDate(item.due_date)}
                                </span>
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </ChartCard>
    );
}
