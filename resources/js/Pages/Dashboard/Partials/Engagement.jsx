import BarChart from '@/Components/Charts/BarChart';
import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';
import { useState } from 'react';

const periods = [
    { key: 'monthly', label: 'Monthly' },
    { key: 'annually', label: 'Annually' },
];

export default function Engagement({ data }) {
    const [period, setPeriod] = useState('monthly');

    const toggle = (
        <div
            role="group"
            aria-label="Chart period"
            className="inline-flex shrink-0 rounded-full bg-slate-100 p-0.5 text-xs font-semibold"
        >
            {periods.map(({ key, label }) => (
                <button
                    key={key}
                    type="button"
                    aria-pressed={period === key}
                    onClick={() => setPeriod(key)}
                    className={`rounded-full px-3 py-1 transition focus-visible:outline-2 focus-visible:outline-primary-600 ${
                        period === key ? 'bg-white text-primary-700 shadow-xs' : 'text-gray-500 hover:text-gray-700'
                    }`}
                >
                    {label}
                </button>
            ))}
        </div>
    );

    const series = data?.[period];

    return (
        <ChartCard
            title="Pipeline Engagement"
            caption="New leads over time"
            action={data ? toggle : null}
        >
            {data === null ? (
                <Skeleton className="h-56 w-full" />
            ) : series.bars.length === 0 ? (
                <EmptyState>No lead activity yet</EmptyState>
            ) : (
                <BarChart
                    data={series.bars}
                    highlight={series.highlight}
                    title={`New leads, ${period}`}
                />
            )}
        </ChartCard>
    );
}
