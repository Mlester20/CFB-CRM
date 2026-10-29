import DonutChart from '@/Components/Charts/DonutChart';
import ChartCard from '@/Components/Dashboard/ChartCard';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';

export default function LeadSources({ data }) {
    return (
        <ChartCard title="Leads by Source" caption="Where your leads come from">
            {data === null ? (
                <Skeleton className="h-40 w-full" />
            ) : data.items.length === 0 ? (
                <EmptyState>No leads yet</EmptyState>
            ) : (
                <DonutChart data={data.items} title="Leads by source" centerLabel="Leads" />
            )}
        </ChartCard>
    );
}
