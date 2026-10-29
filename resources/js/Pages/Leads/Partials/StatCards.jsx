import Card from '@/Components/Dashboard/Card';
import { formatNumber, formatPhp } from '@/Utils/format';

// Static class names so Tailwind can see them.
const grids = {
    1: 'grid-cols-1',
    4: 'grid-cols-2 xl:grid-cols-4',
};

/**
 * "Total leads" is shown to everyone. The three money cards exist only when
 * the server sent the aggregates (dashboard.view-revenue).
 */
export default function StatCards({ stats }) {
    const cards = [{ label: 'Total leads', value: formatNumber(stats.total), hint: 'In your view' }];

    if (stats.open_pipeline !== undefined) {
        cards.push(
            { label: 'Open pipeline', value: formatPhp(stats.open_pipeline), hint: 'New, qualified and proposal' },
            { label: 'Won value', value: formatPhp(stats.won_value), hint: 'Closed-won leads' },
            { label: 'Avg deal size', value: formatPhp(stats.avg_deal_size), hint: 'Across all your leads' },
        );
    }

    return (
        <dl className={`grid gap-4 ${grids[cards.length === 1 ? 1 : 4]}`}>
            {cards.map((card) => (
                <Card key={card.label} as="div" className="min-w-0">
                    <dt className="text-sm font-semibold text-gray-500">{card.label}</dt>
                    <dd className="mt-2 break-words text-2xl font-bold tracking-tight text-gray-900 sm:text-[1.65rem]">
                        {card.value}
                    </dd>
                    <p className="mt-1 text-xs text-gray-500">{card.hint}</p>
                </Card>
            ))}
        </dl>
    );
}
