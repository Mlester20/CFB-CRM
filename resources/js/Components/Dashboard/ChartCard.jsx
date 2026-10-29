import Card from '@/Components/Dashboard/Card';
import { Badge } from '@/Components/Ui';
import { useId } from 'react';

/**
 * A card with a heading row (title, optional caption and action) and a body.
 * `sample` marks a widget that still shows sample data because its module does not exist yet.
 */
export default function ChartCard({ title, caption, action, sample = false, className = '', children }) {
    const id = useId();

    return (
        <Card aria-labelledby={id} className={className}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 id={id} className="text-base font-bold text-gray-900">
                            {title}
                        </h2>
                        {sample && <Badge tone="amber">Sample data</Badge>}
                    </div>
                    {caption && <p className="mt-0.5 text-xs text-gray-500">{caption}</p>}
                </div>
                {action}
            </div>
            <div className="mt-4">{children}</div>
        </Card>
    );
}
