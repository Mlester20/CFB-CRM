import Drawer from '@/Components/Drawer';
import { Button, ButtonLink, Icon, icons } from '@/Components/Ui';
import usePermission from '@/Hooks/usePermission';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LeadDetails from '@/Pages/Leads/Partials/LeadDetails';
import { formatPhp } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Board from './Partials/Board';
import StatCards from './Partials/StatCards';
import Toolbar from './Partials/Toolbar';

/** Filters that are set, as a query-string object. */
function filterParams(filters) {
    const params = {};

    ['search', 'priority'].forEach((key) => {
        if (filters[key]) params[key] = filters[key];
    });

    return params;
}

export default function Index({ board, stats: serverStats, filters, options, canMove, drawer }) {
    const { can } = usePermission();

    const [stats, setStats] = useState(serverStats);
    const [search, setSearch] = useState(filters.search ?? '');
    const [toast, setToast] = useState(null);

    useEffect(() => setStats(serverStats), [serverStats]);

    // The drawer keeps its last content while it slides out.
    const lastDetail = useRef(null);
    if (drawer) lastDetail.current = drawer;
    const detail = lastDetail.current;

    const hasFilters = Boolean(filters.search || filters.priority);
    const params = filterParams(filters);

    // Always the latest filters, even inside a debounce timer started before they changed.
    const filtersRef = useRef(filters);
    filtersRef.current = filters;

    const go = (changes) => {
        router.get(route('pipeline.index'), filterParams({ ...filtersRef.current, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const first = useRef(true);
    const skipDebounce = useRef(false);
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return undefined;
        }

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

    const clearFilters = () => {
        skipDebounce.current = search !== '';
        setSearch('');
        go({ search: null, priority: null });
    };

    // Toasts go away by themselves.
    useEffect(() => {
        if (!toast) return undefined;

        const timer = setTimeout(() => setToast(null), 6000);

        return () => clearTimeout(timer);
    }, [toast]);

    const closeDrawer = () =>
        router.get(route('pipeline.index', params), {}, { preserveState: true, preserveScroll: true, only: ['drawer'] });

    const subtitle = `${stats.total_leads} ${stats.total_leads === 1 ? 'lead' : 'leads'}${
        stats.open_value !== undefined ? ` - ${formatPhp(stats.open_value)} in play` : ''
    }`;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-gray-900">Pipeline</h1>
                    <p className="mt-1 text-sm text-gray-600">{subtitle}</p>
                </div>
            }
        >
            <Head title="Pipeline" />

            <div className="mx-auto max-w-[96rem] space-y-6">
                <StatCards stats={stats} />

                <Toolbar
                    filters={filters}
                    options={options}
                    search={search}
                    onSearch={setSearch}
                    onFilter={go}
                    onClear={clearFilters}
                    hasFilters={hasFilters}
                    note={
                        hasFilters && canMove
                            ? 'Filtered: cards you move go to the top of the column.'
                            : canMove
                              ? null
                              : 'View only: you cannot move deals.'
                    }
                />

                <Board
                    board={board}
                    stages={options.stages}
                    canMove={canMove}
                    filters={filters}
                    filtersActive={hasFilters}
                    hrefFor={(card) => route('pipeline.show', { lead: card.id, ...params })}
                    onStats={setStats}
                    onError={setToast}
                />
            </div>

            {toast && (
                <div role="alert" className="fixed inset-x-4 bottom-4 z-[60] mx-auto flex max-w-md items-start gap-3 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
                    <Icon path={icons.warning} className="mt-0.5 h-5 w-5 shrink-0" />
                    <p className="flex-1">{toast}</p>
                    <button type="button" onClick={() => setToast(null)} aria-label="Dismiss" className="rounded-md p-0.5 hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white">
                        <Icon path={icons.close} className="h-4 w-4" />
                    </button>
                </div>
            )}

            <Drawer
                open={Boolean(drawer)}
                onClose={closeDrawer}
                title={detail?.lead.name}
                subtitle={detail?.lead.company}
                footer={
                    detail ? (
                        <>
                            {detail.lead.can.update && (
                                <ButtonLink href={route('leads.edit', { lead: detail.lead.id })}>
                                    <Icon path={icons.pencil} className="h-4 w-4" />
                                    Edit
                                </ButtonLink>
                            )}
                            <Button variant="secondary" className="ms-auto" onClick={closeDrawer}>
                                Close
                            </Button>
                        </>
                    ) : null
                }
            >
                {detail && (
                    <div className="space-y-6">
                        <LeadDetails lead={detail.lead} options={options} />

                        {can('ai.summary') && (
                            <div>
                                <Button variant="secondary" disabled title="AI coming soon" aria-describedby="ai-soon">
                                    <Icon path={icons.sparkles} className="h-4 w-4" />
                                    AI suggest next step
                                </Button>
                                <p id="ai-soon" className="mt-1.5 text-xs text-gray-500">
                                    AI coming soon
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </Drawer>
        </AuthenticatedLayout>
    );
}
