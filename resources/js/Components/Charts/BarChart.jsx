import useContainerWidth from '@/Hooks/useContainerWidth';
import { formatPercent } from '@/Utils/format';
import { useEffect, useId, useState } from 'react';

/**
 * Responsive bar chart. data: [{ label, value, change_percent }].
 * The active bar (hover, focus, or `highlight` by default) shows a tooltip.
 */
export default function BarChart({ data, highlight = data.length - 1, title, description, height = 230 }) {
    const [ref, width] = useContainerWidth(480);
    const [active, setActive] = useState(highlight);
    const id = useId();

    useEffect(() => setActive(highlight), [data, highlight]);

    const pad = { top: 40, right: 4, bottom: 28, left: 4 };
    const innerWidth = Math.max(width - pad.left - pad.right, 60);
    const innerHeight = height - pad.top - pad.bottom;
    const max = Math.max(...data.map((d) => d.value), 1);
    const slot = innerWidth / data.length;
    const barWidth = Math.min(slot * 0.62, 30);
    const tooltipWidth = 70;

    return (
        <div ref={ref} className="w-full">
            <svg
                key={data.map((d) => d.label).join('-')}
                width={width}
                height={height}
                role="img"
                aria-labelledby={`${id}-title ${id}-desc`}
                className="block overflow-visible"
            >
                <title id={`${id}-title`}>{title}</title>
                <desc id={`${id}-desc`}>
                    {description ?? data.map((d) => `${d.label}: ${d.value}`).join('; ')}
                </desc>

                {[0.25, 0.5, 0.75, 1].map((step) => (
                    <line
                        key={step}
                        x1={pad.left}
                        x2={width - pad.right}
                        y1={pad.top + innerHeight * (1 - step)}
                        y2={pad.top + innerHeight * (1 - step)}
                        className="stroke-slate-200"
                        strokeDasharray="3 5"
                    />
                ))}

                {data.map((d, index) => {
                    const barHeight = Math.max((d.value / max) * innerHeight, 3);
                    const x = pad.left + slot * index + (slot - barWidth) / 2;
                    const y = pad.top + innerHeight - barHeight;
                    const isActive = index === active;

                    return (
                        <g
                            key={d.label}
                            tabIndex={0}
                            role="img"
                            aria-label={`${d.label}: ${d.value} new leads, ${formatPercent(d.change_percent)} from the previous period`}
                            onMouseEnter={() => setActive(index)}
                            onFocus={() => setActive(index)}
                            className="cursor-pointer outline-none focus-visible:[&>rect:first-child]:stroke-primary-600"
                        >
                            <rect
                                x={pad.left + slot * index}
                                y={pad.top - 28}
                                width={slot}
                                height={innerHeight + 28}
                                fill="transparent"
                                stroke="none"
                            />
                            <rect
                                x={x}
                                y={y}
                                width={barWidth}
                                height={barHeight}
                                rx={Math.min(barWidth / 2, 10)}
                                className={`chart-bar transition-colors ${
                                    isActive ? 'fill-primary-700' : 'fill-sky-200'
                                }`}
                                style={{ animationDelay: `${index * 40}ms` }}
                            />
                            <text
                                x={x + barWidth / 2}
                                y={height - 8}
                                textAnchor="middle"
                                className={`text-[11px] ${isActive ? 'fill-gray-900 font-semibold' : 'fill-gray-500'}`}
                            >
                                {d.label}
                            </text>
                        </g>
                    );
                })}

                {data[active] && (() => {
                    const d = data[active];
                    const barHeight = Math.max((d.value / max) * innerHeight, 3);
                    const centerX = pad.left + slot * active + slot / 2;
                    const tipX = Math.min(Math.max(centerX - tooltipWidth / 2, 0), width - tooltipWidth);
                    const tipY = Math.max(pad.top + innerHeight - barHeight - 34, 0);

                    return (
                        <g pointerEvents="none" className="chart-fade">
                            <rect
                                x={tipX}
                                y={tipY}
                                width={tooltipWidth}
                                height={26}
                                rx={8}
                                className="fill-gray-900"
                            />
                            <text
                                x={tipX + tooltipWidth / 2}
                                y={tipY + 17}
                                textAnchor="middle"
                                className="fill-white text-[12px] font-semibold"
                            >
                                {formatPercent(d.change_percent)}
                            </text>
                        </g>
                    );
                })()}
            </svg>
        </div>
    );
}
