import { formatNumber } from '@/Utils/format';
import { Link } from '@inertiajs/react';

/** Page numbers around the current page, with "…" for the gaps. */
function windowOf(current, last) {
    const pages = new Set([1, last, current - 1, current, current + 1]);
    const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    const result = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) result.push('gap-' + page);
        result.push(page);
    });

    return result;
}

/**
 * Pagination from SAIMS's meta ({ current_page, last_page, from, to, total }).
 * pageUrl(page) gives the URL of a page, which keeps the filters.
 */
export default function SaimsPager({ meta, pageUrl }) {
    if (meta.last_page <= 1) {
        return (
            <p className="border-t border-gray-100 px-5 py-4 text-sm text-gray-600">
                Showing {formatNumber(meta.from)}–{formatNumber(meta.to)} of {formatNumber(meta.total)}
            </p>
        );
    }

    const link = (page, label, disabled = false, current = false) =>
        disabled ? (
            <span key={label + page} className="rounded-md px-3 py-1.5 text-sm text-gray-400">
                {label}
            </span>
        ) : (
            <Link
                key={label + page}
                href={pageUrl(page)}
                preserveState
                preserveScroll
                aria-current={current ? 'page' : undefined}
                className={`rounded-md px-3 py-1.5 text-sm ${
                    current ? 'bg-primary-600 font-semibold text-white' : 'text-gray-700 hover:bg-gray-100'
                }`}
            >
                {label}
            </Link>
        );

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4"
            aria-label="Pagination"
        >
            <p className="text-sm text-gray-600">
                Showing {formatNumber(meta.from)}–{formatNumber(meta.to)} of {formatNumber(meta.total)}
            </p>
            <div className="flex flex-wrap gap-1">
                {link(meta.current_page - 1, 'Previous', meta.current_page <= 1)}
                {windowOf(meta.current_page, meta.last_page).map((page) =>
                    typeof page === 'string' ? (
                        <span key={page} className="px-1 py-1.5 text-sm text-gray-400" aria-hidden="true">
                            …
                        </span>
                    ) : (
                        link(page, String(page), false, page === meta.current_page)
                    ),
                )}
                {link(meta.current_page + 1, 'Next', meta.current_page >= meta.last_page)}
            </div>
        </nav>
    );
}
