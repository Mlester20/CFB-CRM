import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const FILTER_KEYS = ['search', 'customer_id', 'status', 'from', 'to'];

/** The filters (and page) that are not empty, as a query-string object. */
export function listParams(filters) {
    const params = {};

    FILTER_KEYS.forEach((key) => {
        if (filters[key] !== undefined && filters[key] !== null && filters[key] !== '') {
            params[key] = filters[key];
        }
    });

    if (filters.page > 1) {
        params.page = filters.page;
    }

    return params;
}

/**
 * Filters, search and page of a SAIMS list live in the URL. This keeps them
 * in sync: go() changes some filters (and goes back to page 1), the search
 * box is debounced, and a visit in progress is reported as `loading`.
 */
export default function useListQuery(routeName, filters) {
    // Always the latest filters, even inside a timer that started before they changed.
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const first = useRef(true);
    const skipDebounce = useRef(false);

    const go = (changes) => {
        router.get(route(routeName), listParams({ ...filtersRef.current, page: 1, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return undefined;
        }

        // A change that already navigates by itself (Clear) must not start a second visit.
        if (skipDebounce.current) {
            skipDebounce.current = false;

            return undefined;
        }

        if (search === (filtersRef.current.search ?? '')) return undefined;

        const timer = setTimeout(() => {
            if (search !== (filtersRef.current.search ?? '')) go({ search: search.trim() || null });
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        const stop = [router.on('start', () => setLoading(true)), router.on('finish', () => setLoading(false))];

        return () => stop.forEach((off) => off());
    }, []);

    const clear = (extra = {}) => {
        skipDebounce.current = search !== '';
        setSearch('');
        go({ search: null, customer_id: null, status: null, from: null, to: null, ...extra });
    };

    const hasFilters = FILTER_KEYS.some((key) => filters[key] !== undefined && filters[key] !== null && filters[key] !== '');
    const params = listParams(filters);

    return {
        go,
        clear,
        loading,
        search,
        setSearch,
        hasFilters,
        params,
        listUrl: () => route(routeName, params),
        detailUrl: (detailRoute, id) => route(detailRoute, { id, ...params }),
        pageUrl: (page) => route(routeName, listParams({ ...filters, page })),
    };
}
