import Card from '@/Components/Dashboard/Card';
import StatBadge from '@/Components/Dashboard/StatBadge';
import { WidgetSkeleton } from '@/Components/Dashboard/States';
import { formatPhpCompact } from '@/Utils/format';
import { useId } from 'react';

export default function WeeklyRevenue({ data }) {
    const id = useId();

    return (
        <Card aria-labelledby={id}>
            <div className="flex items-center justify-between gap-3">
                <h2 id={id} className="text-base font-bold text-gray-900">
                    Weekly Revenue
                </h2>
                {data && <StatBadge value={data.change_percent} />}
            </div>
            <p className="mt-0.5 text-xs text-gray-500">Last 7 days</p>

            {data === null ? (
                <div className="mt-4">
                    <WidgetSkeleton lines={0} />
                </div>
            ) : (
                <p className="mt-3 text-3xl font-bold tracking-tight text-gray-900">
                    {formatPhpCompact(data.amount)}
                </p>
            )}
        </Card>
    );
}
