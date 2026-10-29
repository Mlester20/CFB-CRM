import { formatPercent } from '@/Utils/format';

/** Percent-change badge: green with an up arrow, red with a down arrow. */
export default function StatBadge({ value, tone, className = '' }) {
    const positive = value >= 0;
    const colors =
        tone === 'inverse'
            ? 'bg-white/20 text-white'
            : positive
              ? 'bg-emerald-50 text-emerald-700'
              : 'bg-rose-50 text-rose-700';

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${colors} ${className}`}
        >
            <svg className="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                {positive ? <path d="M6 2 10.5 9h-9z" /> : <path d="M6 10 1.5 3h9z" />}
            </svg>
            <span className="sr-only">{positive ? 'Up' : 'Down'} </span>
            {formatPercent(value)}
        </span>
    );
}
