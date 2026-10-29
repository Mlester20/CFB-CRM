import { formatDate, formatPhp } from '@/Utils/format';

/** One label/value row of a detail drawer. Values are plain text (React escapes them). */
export function Row({ label, children }) {
    return (
        <div className="grid grid-cols-3 gap-3 py-3">
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="col-span-2 min-w-0 break-words text-sm text-gray-900">
                {children ?? <span className="text-gray-400">Not set</span>}
            </dd>
        </div>
    );
}

export function Section({ title, children }) {
    return (
        <section className="mt-6 border-t border-slate-100 pt-5">
            <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
            <div className="mt-3">{children}</div>
        </section>
    );
}

/** A money line; null shows as a dash. */
export function Money({ value }) {
    return value === null || value === undefined ? <span className="text-gray-400">—</span> : formatPhp(value);
}

/** "2026-10-27" or "2026-10-27 08:15:00" as "27 Oct 2026"; anything else as is. */
export function DateText({ value }) {
    if (!value) return null;

    const day = String(value).slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(day) ? formatDate(day) : String(value);
}
