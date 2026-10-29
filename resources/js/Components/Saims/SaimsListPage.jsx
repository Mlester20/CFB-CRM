import Card from '@/Components/Dashboard/Card';
import SaimsErrorState from '@/Components/Saims/SaimsErrorState';
import SaimsPager from '@/Components/Saims/SaimsPager';
import { Badge, Button, Icon, icons, inputClass } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatNumber } from '@/Utils/format';
import { Head } from '@inertiajs/react';

/**
 * The frame shared by the four SAIMS pages: header, toolbar card, the table
 * (or an error / empty / loading state) and the pager. Read-only: nothing in
 * here changes data.
 */
export default function SaimsListPage({
    title,
    subtitle,
    query,
    records,
    error,
    searchPlaceholder,
    filters,
    emptyText = 'Nothing to show yet.',
    children,
    drawer,
}) {
    const { loading, search, setSearch, hasFilters, clear, pageUrl } = query;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900">{title}</h1>
                        <p className="mt-1 text-sm text-gray-600">{subtitle}</p>
                    </div>
                    <Badge tone="blue">Read-only · from SAIMS</Badge>
                </div>
            }
        >
            <Head title={title} />

            <div className="mx-auto max-w-[96rem] space-y-6">
                {!error && (
                    <Card as="div" className="p-4!">
                        <div className="flex flex-wrap items-end gap-3">
                            {searchPlaceholder && (
                                <div className="relative min-w-52 flex-1 sm:max-w-sm">
                                    <label htmlFor="saims-search" className="sr-only">
                                        {searchPlaceholder}
                                    </label>
                                    <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                                        <Icon path={icons.search} />
                                    </span>
                                    <input
                                        id="saims-search"
                                        type="search"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder={searchPlaceholder}
                                        className={`${inputClass()} py-2 ps-10`}
                                    />
                                </div>
                            )}

                            {filters}

                            {hasFilters && (
                                <button
                                    type="button"
                                    onClick={() => clear()}
                                    className="rounded-md px-2 py-2 text-sm font-medium text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    Clear filters
                                </button>
                            )}

                            {records && (
                                <p className="ms-auto text-sm text-gray-500" aria-live="polite">
                                    <span className="font-semibold text-gray-900">{formatNumber(records.meta.total)}</span>{' '}
                                    {records.meta.total === 1 ? 'result' : 'results'}
                                </p>
                            )}
                        </div>
                    </Card>
                )}

                <Card as="div" className="relative overflow-hidden p-0!" aria-busy={loading}>
                    {error ? (
                        <SaimsErrorState error={error} />
                    ) : records.items.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <h2 className="text-base font-bold text-gray-900">{hasFilters ? 'No results' : 'No records'}</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                {hasFilters ? 'Nothing in SAIMS matches your search or filters.' : emptyText}
                            </p>
                            {hasFilters && (
                                <Button variant="secondary" className="mt-5" onClick={() => clear()}>
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className={loading ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                                {loading && <span className="sr-only" role="status">Loading…</span>}
                                {children}
                            </div>
                            <SaimsPager meta={records.meta} pageUrl={pageUrl} />
                        </>
                    )}
                </Card>
            </div>

            {drawer}
        </AuthenticatedLayout>
    );
}
