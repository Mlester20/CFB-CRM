import useContainerWidth from '@/Hooks/useContainerWidth';
import { useId } from 'react';

/** Smooth path through the points (Catmull-Rom converted to Bezier curves). */
function smoothPath(points) {
    if (points.length < 2) {
        return '';
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;

        const c1x = p1.x + (p2.x - p0.x) / 6;
        const c1y = p1.y + (p2.y - p0.y) / 6;
        const c2x = p2.x - (p3.x - p1.x) / 6;
        const c2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`;
    }

    return d;
}

/**
 * Responsive smooth line/area chart. data: [{ label, value }].
 * `formatValue` is used only for the text alternative.
 */
export default function AreaChart({ data, title, description, formatValue = String, height = 150 }) {
    const [ref, width] = useContainerWidth(320);
    const id = useId();

    const pad = { top: 12, right: 10, bottom: 24, left: 10 };
    const innerWidth = Math.max(width - pad.left - pad.right, 60);
    const innerHeight = height - pad.top - pad.bottom;
    const max = Math.max(...data.map((d) => d.value), 1);
    const step = data.length > 1 ? innerWidth / (data.length - 1) : 0;

    const points = data.map((d, i) => ({
        x: pad.left + step * i,
        y: pad.top + innerHeight - (d.value / max) * innerHeight,
    }));

    const line = smoothPath(points);
    const baseline = pad.top + innerHeight;
    const area = points.length > 1
        ? `${line} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z`
        : '';
    const last = points[points.length - 1];
    const labelEvery = Math.max(Math.ceil(data.length / Math.max(Math.floor(width / 56), 1)), 1);

    return (
        <div ref={ref} className="w-full">
            <svg
                width={width}
                height={height}
                role="img"
                aria-labelledby={`${id}-title ${id}-desc`}
                className="block overflow-visible"
            >
                <title id={`${id}-title`}>{title}</title>
                <desc id={`${id}-desc`}>
                    {description ?? data.map((d) => `${d.label}: ${formatValue(d.value)}`).join('; ')}
                </desc>

                <defs>
                    <linearGradient id={`${id}-fill`} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" className="text-sky-400" stopColor="currentColor" stopOpacity="0.4" />
                        <stop offset="100%" className="text-sky-400" stopColor="currentColor" stopOpacity="0" />
                    </linearGradient>
                </defs>

                <line
                    x1={pad.left}
                    x2={width - pad.right}
                    y1={baseline}
                    y2={baseline}
                    className="stroke-slate-200"
                />

                {area && <path d={area} fill={`url(#${id}-fill)`} className="chart-fade" />}
                {line && (
                    <path
                        d={line}
                        pathLength="1"
                        fill="none"
                        strokeWidth="3"
                        strokeLinecap="round"
                        className="chart-line stroke-sky-500"
                    />
                )}

                {last && (
                    <g className="chart-fade" style={{ animationDelay: '0.8s' }}>
                        <circle cx={last.x} cy={last.y} r="9" className="fill-sky-500/20" />
                        <circle cx={last.x} cy={last.y} r="4.5" className="fill-white stroke-primary-700" strokeWidth="2.5" />
                    </g>
                )}

                {data.map((d, i) =>
                    i === 0 || i === data.length - 1 || (i % labelEvery === 0 && data.length - 1 - i >= labelEvery) ? (
                        <text
                            key={d.label}
                            x={points[i].x}
                            y={height - 6}
                            textAnchor={i === 0 ? 'start' : i === data.length - 1 ? 'end' : 'middle'}
                            className="fill-gray-500 text-[11px]"
                        >
                            {d.label}
                        </text>
                    ) : null,
                )}
            </svg>
        </div>
    );
}
