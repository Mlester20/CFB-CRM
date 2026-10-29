import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, WidgetSkeleton } from '@/Components/Dashboard/States';
import { Icon, icons } from '@/Components/Ui';
import { formatDate } from '@/Utils/format';

const priorityStyles = {
    High: 'bg-rose-50 text-rose-700 ring-rose-200',
    Medium: 'bg-amber-50 text-amber-800 ring-amber-200',
    Low: 'bg-sky-50 text-sky-700 ring-sky-200',
};

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
                            <div className="flex items-start gap-2">
                                {item.overdue && (
                                    <Icon path={icons.warning} className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                                )}
                                <p className="min-w-0 flex-1 text-sm font-semibold text-gray-900">{item.title}</p>
                                <span
                                    className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset ${
                                        priorityStyles[item.priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
                                    }`}
                                >
                                    <span className="sr-only">Priority: </span>
                                    {item.priority}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-gray-600">
                                {item.contact} ·{' '}
                                <span className={`whitespace-nowrap ${item.overdue ? 'font-semibold text-rose-700' : ''}`}>
                                    {item.overdue && <span className="sr-only">Overdue: </span>}
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
