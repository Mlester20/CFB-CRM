/** Friendly empty state for a widget with nothing to show. */
export function EmptyState({ children = 'No data yet' }) {
    return (
        <p className="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-gray-500">
            {children}
        </p>
    );
}

/** Animated placeholder block for a widget that is still loading. */
export function Skeleton({ className = '' }) {
    return <div className={`animate-pulse rounded-lg bg-slate-100 ${className}`} aria-hidden="true" />;
}

/** Loading state for a whole widget body. */
export function WidgetSkeleton({ lines = 3 }) {
    return (
        <div role="status" aria-busy="true" aria-live="polite" className="space-y-3">
            <span className="sr-only">Loading…</span>
            <Skeleton className="h-8 w-2/3" />
            {Array.from({ length: lines }, (_, i) => (
                <Skeleton key={i} className="h-4 w-full" />
            ))}
        </div>
    );
}
