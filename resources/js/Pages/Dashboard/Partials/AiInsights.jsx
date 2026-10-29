import ChartCard from '@/Components/Dashboard/ChartCard';
import ComingSoon from '@/Components/Dashboard/ComingSoon';
import { EmptyState, WidgetSkeleton } from '@/Components/Dashboard/States';
import { Icon, icons } from '@/Components/Ui';

export default function AiInsights({ data }) {
    return (
        <ChartCard
            title="AI Sales Insights"
            caption="Generated from your pipeline"
            action={
                <span
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                    aria-hidden="true"
                >
                    <Icon path={icons.sparkles} />
                </span>
            }
        >
            {data === null ? (
                <WidgetSkeleton lines={4} />
            ) : !data.summary ? (
                <EmptyState>No insights yet. Run an analysis to get started.</EmptyState>
            ) : (
                <>
                    <p className="text-sm leading-relaxed text-gray-700">{data.summary}</p>
                    <ul className="mt-3 space-y-2">
                        {data.highlights.map((highlight) => (
                            <li key={highlight} className="flex gap-2 text-sm text-gray-600">
                                <span
                                    className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"
                                    aria-hidden="true"
                                />
                                {highlight}
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <div className="mt-5">
                <ComingSoon tooltip="AI analysis coming soon" className="w-full">
                    <Icon path={icons.sparkles} className="h-4 w-4" />
                    Analyze pipeline
                </ComingSoon>
            </div>
        </ChartCard>
    );
}
