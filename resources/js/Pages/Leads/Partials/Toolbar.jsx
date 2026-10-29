import Card from '@/Components/Dashboard/Card';
import { Icon, icons, inputClass } from '@/Components/Ui';
import { formatNumber } from '@/Utils/format';

const selectClass = `${inputClass()} py-2 pe-8`;

/**
 * Search, priority and source filters, stage chips with counts, the "N of M"
 * counter and the table/cards switch. Everything here lives in the URL.
 */
export default function Toolbar({
    filters,
    stageCounts,
    options,
    filteredTotal,
    total,
    search,
    onSearch,
    onFilter,
    onClear,
    hasFilters,
    view,
    onView,
}) {
    const chips = [{ value: null, label: 'All' }, ...options.stages];

    return (
        <Card as="div" className="space-y-4 p-4!">
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative min-w-52 flex-1 sm:max-w-sm">
                    <label htmlFor="lead-search" className="sr-only">
                        Search leads by name, company or email
                    </label>
                    <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                        <Icon path={icons.search} />
                    </span>
                    <input
                        id="lead-search"
                        type="search"
                        value={search}
                        onChange={(e) => onSearch(e.target.value)}
                        placeholder="Search name, company or email"
                        className={`${inputClass()} py-2 ps-10`}
                    />
                </div>

                <div>
                    <label htmlFor="filter-priority" className="sr-only">
                        Priority
                    </label>
                    <select
                        id="filter-priority"
                        value={filters.priority ?? ''}
                        onChange={(e) => onFilter({ priority: e.target.value || null })}
                        className={selectClass}
                    >
                        <option value="">All priorities</option>
                        {options.priorities.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="filter-source" className="sr-only">
                        Source
                    </label>
                    <select
                        id="filter-source"
                        value={filters.source ?? ''}
                        onChange={(e) => onFilter({ source: e.target.value || null })}
                        className={selectClass}
                    >
                        <option value="">All sources</option>
                        {options.sources.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </div>

                {hasFilters && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="rounded-md px-2 py-1 text-sm font-medium text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                    >
                        Clear filters
                    </button>
                )}

                <div className="ms-auto flex items-center gap-3">
                    <p className="text-sm text-gray-500" aria-live="polite">
                        <span className="font-semibold text-gray-900">{formatNumber(filteredTotal)}</span> of{' '}
                        {formatNumber(total)}
                    </p>

                    <div role="group" aria-label="View" className="inline-flex rounded-lg bg-slate-100 p-0.5">
                        {[
                            { key: 'table', label: 'Table view', icon: icons.list },
                            { key: 'cards', label: 'Card view', icon: icons.grid },
                        ].map(({ key, label, icon }) => (
                            <button
                                key={key}
                                type="button"
                                aria-pressed={view === key}
                                aria-label={label}
                                title={label}
                                onClick={() => onView(key)}
                                className={`rounded-md p-1.5 transition focus-visible:outline-2 focus-visible:outline-primary-600 ${
                                    view === key ? 'bg-white text-primary-700 shadow-xs' : 'text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                <Icon path={icon} className="h-5 w-5" />
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            <div role="group" aria-label="Filter by stage" className="flex flex-wrap gap-2">
                {chips.map((chip) => {
                    const active = (filters.stage ?? null) === chip.value;
                    const count = stageCounts[chip.value ?? 'all'];

                    return (
                        <button
                            key={chip.label}
                            type="button"
                            aria-pressed={active}
                            onClick={() => onFilter({ stage: chip.value })}
                            className={`inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${
                                active
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'bg-white text-gray-700 ring-1 ring-slate-200 hover:bg-slate-50'
                            }`}
                        >
                            {chip.label}
                            <span
                                className={`rounded-full px-1.5 text-xs font-bold ${
                                    active ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'
                                }`}
                            >
                                {formatNumber(count)}
                            </span>
                        </button>
                    );
                })}
            </div>
        </Card>
    );
}
