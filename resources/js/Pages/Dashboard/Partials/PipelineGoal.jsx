import { WidgetSkeleton } from '@/Components/Dashboard/States';
import { formatNumber, formatPhp } from '@/Utils/format';
import { useId } from 'react';

export default function PipelineGoal({ data }) {
    const id = useId();

    return (
        <section
            aria-labelledby={id}
            className="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary-800 via-primary-700 to-sky-500 p-5 text-white shadow-[0_10px_30px_-10px_rgba(29,78,216,0.55)]"
        >
            <div
                className="pointer-events-none absolute -end-10 -top-10 h-40 w-40 rounded-full bg-white/10"
                aria-hidden="true"
            />
            <div
                className="pointer-events-none absolute -bottom-16 end-6 h-32 w-32 rounded-full bg-sky-300/20"
                aria-hidden="true"
            />

            <div className="relative flex items-center justify-between gap-3">
                <h2 id={id} className="text-sm font-semibold text-sky-100">
                    Pipeline Goal
                </h2>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-bold tracking-wider">
                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-300" aria-hidden="true" />
                    LIVE
                </span>
            </div>

            {data === null ? (
                <div className="relative mt-4 opacity-70">
                    <WidgetSkeleton lines={1} />
                </div>
            ) : (
                <div className="relative mt-4">
                    <p className="text-[1.65rem] font-bold leading-tight tracking-tight">
                        {formatPhp(data.total)}
                    </p>
                    <p className="mt-1 text-sm text-sky-100">
                        Value of {formatNumber(data.deals_count)} open {data.deals_count === 1 ? 'lead' : 'leads'}
                    </p>
                </div>
            )}
        </section>
    );
}
