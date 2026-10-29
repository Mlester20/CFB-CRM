import Card from '@/Components/Dashboard/Card';
import { formatNumber, formatPhp } from '@/Utils/format';

/**
 * Total pipeline, Open deals, Won value, Win rate. The two money cards exist only
 * when the server sent the aggregates (dashboard.view-revenue).
 */
export default function StatCards({ stats }) {
    const money = stats.total_pipeline !== undefined;

    const cards = [
        money && { label: 'Total pipeline', value: formatPhp(stats.total_pipeline), hint: 'Every lead in your view' },
        { label: 'Open deals', value: formatNumber(stats.open_deals), hint: 'New, qualified and proposal' },
        money && { label: 'Won value', value: formatPhp(stats.won_value), hint: 'Closed-won leads' },
        { label: 'Win rate', value: `${stats.win_rate}%`, hint: 'Won of won and lost' },
    ].filter(Boolean);

    return (
        <dl className={`grid gap-4 ${cards.length === 4 ? 'grid-cols-2 xl:grid-cols-4' : 'grid-cols-2'}`}>
            {cards.map((card) => (
                <Card key={card.label} as="div" className="min-w-0">
                    <dt className="text-sm font-semibold text-gray-500">{card.label}</dt>
                    <dd className="mt-2 break-words text-xl font-bold tracking-tight text-gray-900 2xl:text-2xl">
                        {card.value}
                    </dd>
                    <p className="mt-1 text-xs text-gray-500">{card.hint}</p>
                </Card>
            ))}
        </dl>
    );
}
