import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';
import { fallbackStageColor, stageColors } from '@/Components/Dashboard/stages';
import { formatNumber, formatPhp } from '@/Utils/format';

export default function PipelineStages({ data }) {
    return (
        <ChartCard title="Pipeline by Stage" caption="Leads per stage">
            {data === null ? (
                <div className="space-y-4" role="status" aria-busy="true">
                    <span className="sr-only">Loading…</span>
                    {[0, 1, 2, 3, 4].map((i) => (
                        <Skeleton key={i} className="h-9 w-full" />
                    ))}
                </div>
            ) : data.items.length === 0 ? (
                <EmptyState>No leads in the pipeline yet</EmptyState>
            ) : (
                <ul className="space-y-4">
                    {data.items.map((item) => {
                        const color = stageColors[item.stage] ?? fallbackStageColor;
                        const percent = Math.round(item.percent);

                        return (
                            <li key={item.stage}>
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                    <p className="flex items-center gap-2 text-sm">
                                        <span className={`h-2.5 w-2.5 rounded-full ${color.dot}`} aria-hidden="true" />
                                        <span className="font-semibold text-gray-900">{item.stage}</span>
                                        <span className="text-gray-500">
                                            {formatNumber(item.count)} {item.count === 1 ? 'lead' : 'leads'}
                                        </span>
                                    </p>
                                    <p className="text-sm">
                                        {/* The peso value only arrives for users who may see revenue. */}
                                        {item.value !== undefined && (
                                            <span className="font-semibold text-gray-900">
                                                {formatPhp(item.value)}
                                                <span className="mx-1.5 text-gray-300" aria-hidden="true">·</span>
                                            </span>
                                        )}
                                        <span className="text-gray-500">{percent}%</span>
                                    </p>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label={`${item.stage} share of the pipeline`}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={percent}
                                    className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        className={`chart-grow-x h-full rounded-full ${color.bar}`}
                                        style={{ width: `${item.percent}%` }}
                                    />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </ChartCard>
    );
}
