import Card from '@/Components/Dashboard/Card';
import Drawer from '@/Components/Drawer';
import { Button, ButtonAnchor, ButtonLink, Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDelete from './Partials/ConfirmDelete';
import LeadCards from './Partials/LeadCards';
import LeadDetails from './Partials/LeadDetails';
import LeadForm from './Partials/LeadForm';
import LeadsTable from './Partials/LeadsTable';
import StatCards from './Partials/StatCards';
import Toolbar from './Partials/Toolbar';

/** Filters that are not the default, as a query-string object. Defaults stay out of the URL. */
function filterParams(filters) {
    const params = {};

    ['search', 'stage', 'priority', 'source'].forEach((key) => {
        if (filters[key]) params[key] = filters[key];
    });

    if (filters.sort !== 'updated' || filters.direction !== 'desc') {
        params.sort = filters.sort;
        params.direction = filters.direction;
    }

    return params;
}

/** Table or cards, remembered in localStorage (which may be unavailable). */
function useViewMode() {
    const [view, setView] = useState(() => {
        try {
            return window.localStorage.getItem('leads.view') === 'cards' ? 'cards' : 'table';
        } catch {
            return 'table';
        }
    });

    const change = (next) => {
        setView(next);

        try {
            window.localStorage.setItem('leads.view', next);
        } catch {
            // The choice still works for this visit.
        }
    };

    return [view, change];
}

const pageLabel = (label) => label.replace('&laquo; ', '').replace(' &raquo;', '');

export default function Index({ leads, filters, stageCounts, stats, options, drawer, owners }) {
    const { can } = usePermission();
    const canBulk = can('leads.delete');

    const [view, setView] = useViewMode();
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState([]);
    const [target, setTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [loading, setLoading] = useState(false);

    // The detail drawer (mode "show") and the add/edit modal (mode "create" / "edit")
    // each keep their last content while they animate out.
    const lastDetail = useRef(null);
    const lastForm = useRef(null);
    if (drawer?.mode === 'show') lastDetail.current = drawer;
    if (drawer && drawer.mode !== 'show') lastForm.current = drawer;
    const detail = lastDetail.current;
    const form = lastForm.current;

    const rows = leads.data;
    const pageIds = rows.map((lead) => lead.id);
    const hasFilters = Boolean(filters.search || filters.stage || filters.priority || filters.source);

    // Everything in the URL: filters, sort and the page.
    const urlParams = { ...filterParams(filters), ...(leads.current_page > 1 ? { page: leads.current_page } : {}) };
    const urls = (lead) => ({
        show: route('leads.show', { lead: lead.id, ...urlParams }),
        edit: route('leads.edit', { lead: lead.id, ...urlParams }),
    });

    // Always the latest filters, even inside a debounce timer started before they changed.
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    const go = (changes) => {
        router.get(route('leads.index'), filterParams({ ...filtersRef.current, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Live search with a short debounce.
    const first = useRef(true);
    const skipDebounce = useRef(false);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return undefined;
        }

        // A change that already navigates by itself (Clear filters) must not start a second visit.
        if (skipDebounce.current) {
            skipDebounce.current = false;

            return undefined;
        }

        if (search === (filtersRef.current.search ?? '')) return undefined;

        const timer = setTimeout(() => {
            // The filters may have been cleared meanwhile; do nothing then.
            if (search !== (filtersRef.current.search ?? '')) go({ search: search.trim() || null });
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        const stop = [router.on('start', () => setLoading(true)), router.on('finish', () => setLoading(false))];

        return () => stop.forEach((off) => off());
    }, []);

    // Selection only ever holds leads that are on the current page.
    useEffect(() => {
        setSelected((current) => current.filter((id) => pageIds.includes(id)));
    }, [leads.data]);

    const selection = {
        enabled: canBulk,
        selected,
        toggle: (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id])),
        toggleAll: () =>
            setSelected((s) => (pageIds.every((id) => s.includes(id)) ? s.filter((id) => !pageIds.includes(id)) : [...new Set([...s, ...pageIds])])),
    };

    const sortBy = (column) => {
        const direction = filters.sort === column ? (filters.direction === 'asc' ? 'desc' : 'asc') : column === 'name' ? 'asc' : 'desc';

        go({ sort: column, direction });
    };

    const clearFilters = () => {
        skipDebounce.current = search !== '';
        setSearch('');
        go({ search: null, stage: null, priority: null, source: null });
    };

    const closeDrawer = () =>
        router.get(route('leads.index', urlParams), {}, { preserveState: true, preserveScroll: true });

    const confirmDelete = () => {
        setDeleting(true);

        const options = {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setTarget(null);
                setSelected([]);
            },
        };

        if (target.lead) {
            router.delete(route('leads.destroy', { lead: target.lead.id, ...urlParams }), options);
        } else {
            router.delete(route('leads.bulk-destroy', urlParams), { ...options, data: { ids: selected } });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900">Leads</h1>
                        <p className="mt-1 text-sm text-gray-600">Track and qualify every opportunity.</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {can('leads.export') && (
                            <ButtonAnchor href={route('leads.export', filterParams(filters))}>
                                <Icon path={icons.download} className="h-4 w-4" />
                                Export
                            </ButtonAnchor>
                        )}
                        {can('leads.create') && (
                            <ButtonLink href={route('leads.create', urlParams)} preserveState preserveScroll>
                                <Icon path={icons.plus} className="h-4 w-4" />
                                Add lead
                            </ButtonLink>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Leads" />

            <div className="mx-auto max-w-[96rem] space-y-6">
                <StatCards stats={stats} />

                <Toolbar
                    filters={filters}
                    stageCounts={stageCounts}
                    options={options}
                    filteredTotal={leads.total}
                    total={stats.total}
                    search={search}
                    onSearch={setSearch}
                    onFilter={go}
                    onClear={clearFilters}
                    hasFilters={hasFilters}
                    view={view}
                    onView={setView}
                />

                <Card as="div" className="relative overflow-hidden p-0!" aria-busy={loading}>
                    {selected.length > 0 && (
                        <div
                            role="region"
                            aria-label="Bulk actions"
                            className="flex flex-wrap items-center gap-3 border-b border-primary-100 bg-primary-50 px-5 py-3"
                        >
                            <p className="text-sm font-semibold text-primary-800">{selected.length} selected</p>
                            <button
                                type="button"
                                onClick={() => setSelected([])}
                                className="rounded-md text-sm font-medium text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                            >
                                Clear
                            </button>
                            <Button
                                variant="danger"
                                className="ms-auto"
                                onClick={() => setTarget({ count: selected.length })}
                            >
                                <Icon path={icons.trash} className="h-4 w-4" />
                                Delete selected
                            </Button>
                        </div>
                    )}

                    <div className={loading ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                        {rows.length === 0 ? (
                            <div className="px-6 py-16 text-center">
                                {stats.total === 0 ? (
                                    <>
                                        <h2 className="text-base font-bold text-gray-900">No leads yet</h2>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {can('leads.create')
                                                ? 'Add your first lead to start tracking opportunities.'
                                                : 'Leads you own will show up here.'}
                                        </p>
                                        {can('leads.create') && (
                                            <ButtonLink href={route('leads.create')} className="mt-5">
                                                <Icon path={icons.plus} className="h-4 w-4" />
                                                Add lead
                                            </ButtonLink>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <h2 className="text-base font-bold text-gray-900">No results</h2>
                                        <p className="mt-1 text-sm text-gray-600">No leads match your search or filters.</p>
                                        <Button variant="secondary" className="mt-5" onClick={clearFilters}>
                                            Clear filters
                                        </Button>
                                    </>
                                )}
                            </div>
                        ) : view === 'cards' ? (
                            <LeadCards
                                leads={rows}
                                options={options}
                                urls={urls}
                                selection={selection}
                                onDelete={(lead) => setTarget({ lead })}
                            />
                        ) : (
                            <LeadsTable
                                leads={rows}
                                filters={filters}
                                options={options}
                                urls={urls}
                                selection={selection}
                                onSort={sortBy}
                                onDelete={(lead) => setTarget({ lead })}
                            />
                        )}
                    </div>

                    {leads.last_page > 1 && (
                        <nav
                            className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4"
                            aria-label="Pagination"
                        >
                            <p className="text-sm text-gray-600">
                                Showing {leads.from}–{leads.to} of {leads.total}
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {leads.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            preserveState
                                            preserveScroll
                                            aria-current={link.active ? 'page' : undefined}
                                            className={`rounded-md px-3 py-1.5 text-sm ${
                                                link.active
                                                    ? 'bg-primary-600 font-semibold text-white'
                                                    : 'text-gray-700 hover:bg-gray-100'
                                            }`}
                                        >
                                            {pageLabel(link.label)}
                                        </Link>
                                    ) : (
                                        <span key={index} className="rounded-md px-3 py-1.5 text-sm text-gray-400">
                                            {pageLabel(link.label)}
                                        </span>
                                    ),
                                )}
                            </div>
                        </nav>
                    )}
                </Card>
            </div>

            <Drawer
                open={drawer?.mode === 'show'}
                onClose={closeDrawer}
                title={detail?.lead.name}
                subtitle={detail?.lead.company}
                footer={
                    detail ? (
                        <>
                            {detail.lead.can.update && (
                                <ButtonLink href={route('leads.edit', { lead: detail.lead.id, ...urlParams })} preserveState preserveScroll>
                                    <Icon path={icons.pencil} className="h-4 w-4" />
                                    Edit
                                </ButtonLink>
                            )}
                            {detail.lead.can.delete && (
                                <Button variant="danger" onClick={() => setTarget({ lead: detail.lead })}>
                                    <Icon path={icons.trash} className="h-4 w-4" />
                                    Delete
                                </Button>
                            )}
                            <Button variant="secondary" className="ms-auto" onClick={closeDrawer}>
                                Close
                            </Button>
                        </>
                    ) : null
                }
            >
                {detail && <LeadDetails lead={detail.lead} options={options} />}
            </Drawer>

            <LeadForm
                show={drawer !== null && drawer.mode !== 'show'}
                lead={form?.lead ?? null}
                options={options}
                owners={owners}
                submitUrl={
                    form?.mode === 'edit'
                        ? route('leads.update', { lead: form.lead.id, ...urlParams })
                        : route('leads.store', urlParams)
                }
                onClose={() =>
                    form?.mode === 'edit'
                        ? router.get(urls(form.lead).show, {}, { preserveState: true, preserveScroll: true })
                        : closeDrawer()
                }
            />

            <ConfirmDelete
                target={target}
                processing={deleting}
                onCancel={() => setTarget(null)}
                onConfirm={confirmDelete}
            />
        </AuthenticatedLayout>
    );
}
