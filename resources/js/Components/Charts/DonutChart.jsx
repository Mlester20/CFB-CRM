import { formatNumber } from '@/Utils/format';
import { useId } from 'react';

// Static class names so Tailwind can see them.
const strokes = ['stroke-primary-700', 'stroke-sky-500', 'stroke-sky-300', 'stroke-primary-300', 'stroke-slate-300'];
const dots = ['bg-primary-700', 'bg-sky-500', 'bg-sky-300', 'bg-primary-300', 'bg-slate-300'];

/**
 * Donut chart with a legend. data: [{ label, value }].
 * `centerLabel` is shown under the total in the middle.
 */
export default function DonutChart({ data, title, centerLabel = 'Total', size = 150, stroke = 22 }) {
    const id = useId();
    const total = data.reduce((sum, d) => sum + d.value, 0);
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const gap = data.length > 1 ? 3 : 0;

    let offset = 0;
    const segments = data.map((d, i) => {
        const length = total ? (d.value / total) * circumference : 0;
        const segment = { ...d, length: Math.max(length - gap, 0), offset, index: i };

        offset += length;

        return segment;
    });

    const description = data
        .map((d) => `${d.label}: ${d.value} (${total ? Math.round((d.value / total) * 100) : 0}%)`)
        .join('; ');

    return (
        <div className="@container">
            <div className="flex flex-col items-center gap-5 @sm:flex-row">
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                role="img"
                aria-labelledby={`${id}-title ${id}-desc`}
                className="shrink-0"
            >
                <title id={`${id}-title`}>{title}</title>
                <desc id={`${id}-desc`}>{description}</desc>

                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    className="stroke-slate-100"
                />
                <g className="chart-pop">
                    <g transform={`rotate(-90 ${size / 2} ${size / 2})`}>
                        {segments.map((s) => (
                            <circle
                                key={s.label}
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                fill="none"
                                strokeWidth={stroke}
                                strokeLinecap="butt"
                                strokeDasharray={`${s.length} ${circumference - s.length}`}
                                strokeDashoffset={-s.offset}
                                className={strokes[s.index % strokes.length]}
                            />
                        ))}
                    </g>
                </g>
                <text
                    x="50%"
                    y="50%"
                    textAnchor="middle"
                    dominantBaseline="central"
                    dy="-0.45em"
                    className="fill-gray-900 text-2xl font-bold"
                >
                    {formatNumber(total)}
                </text>
                <text
                    x="50%"
                    y="50%"
                    textAnchor="middle"
                    dominantBaseline="central"
                    dy="1.1em"
                    className="fill-gray-500 text-[11px]"
                >
                    {centerLabel}
                </text>
            </svg>

            <ul className="w-full min-w-0 flex-1 space-y-2.5">
                {segments.map((s) => (
                    <li key={s.label} className="flex items-center gap-2.5 text-sm">
                        <span
                            className={`h-2.5 w-2.5 shrink-0 rounded-full ${dots[s.index % dots.length]}`}
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1 truncate text-gray-700">{s.label}</span>
                        <span className="font-semibold text-gray-900">{formatNumber(s.value)}</span>
                        <span className="w-10 text-end text-xs text-gray-500">
                            {total ? Math.round((s.value / total) * 100) : 0}%
                        </span>
                    </li>
                ))}
            </ul>
            </div>
        </div>
    );
}
