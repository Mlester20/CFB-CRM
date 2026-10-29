import Card from '@/Components/Dashboard/Card';
import StatBadge from '@/Components/Dashboard/StatBadge';
import { WidgetSkeleton } from '@/Components/Dashboard/States';
import { formatNumber } from '@/Utils/format';
import { useId } from 'react';

export default function Conversion({ data }) {
    const id = useId();

    return (
        <Card aria-labelledby={id}>
            <div className="flex items-center justify-between gap-3">
                <h2 id={id} className="text-base font-bold text-gray-900">
                    Conversion
                </h2>
                {data && data.change_percent !== null && <StatBadge value={data.change_percent} />}
            </div>
            <p className="mt-0.5 text-xs text-gray-500">Won ÷ (won + lost)</p>

            {data === null ? (
                <div className="mt-4">
                    <WidgetSkeleton lines={1} />
                </div>
            ) : (
                <>
                    <p className="mt-3 text-3xl font-bold tracking-tight text-gray-900">{data.win_rate.toFixed(1)}%</p>
                    <p className="mt-1 text-sm text-gray-600">
                        {formatNumber(data.leads)} {data.leads === 1 ? 'lead' : 'leads'} · {formatNumber(data.open_leads)} open
                    </p>
                </>
            )}
        </Card>
    );
}
