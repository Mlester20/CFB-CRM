import Card from '@/Components/Dashboard/Card';
import StatBadge from '@/Components/Dashboard/StatBadge';
import { WidgetSkeleton } from '@/Components/Dashboard/States';
import SaimsErrorState from '@/Components/Saims/SaimsErrorState';
import { formatPhpCompact } from '@/Utils/format';
import { useId } from 'react';

/**
 * The last 7 days, always (it ignores the date range). `data.label` says where
 * the number comes from: "Invoiced (incl. VAT)" from SAIMS, or "Won lead value".
 * `null` is loading; `data.error` is a SAIMS failure that only affects this card.
 */
export default function WeeklyRevenue({ data }) {
    const id = useId();
    const failed = data && data.error;

    return (
        <Card aria-labelledby={id}>
            <div className="flex items-center justify-between gap-3">
                <h2 id={id} className="text-base font-bold text-gray-900">
                    Weekly Revenue
                </h2>
                {data && !failed && data.change_percent !== null && <StatBadge value={data.change_percent} />}
            </div>
            <p className="mt-0.5 text-xs text-gray-500">
                Last 7 days{data && !failed ? ` · ${data.label}` : ''}
            </p>

            {data === null ? (
                <div className="mt-4">
                    <WidgetSkeleton lines={0} />
                </div>
            ) : failed ? (
                <SaimsErrorState error={data.error} compact />
            ) : (
                <>
                    <p className="mt-3 text-3xl font-bold tracking-tight text-gray-900">{formatPhpCompact(data.amount)}</p>
                    {data.partial && <p className="mt-1 text-xs text-amber-700">Partial: only the first invoices were read.</p>}
                </>
            )}
        </Card>
    );
}
