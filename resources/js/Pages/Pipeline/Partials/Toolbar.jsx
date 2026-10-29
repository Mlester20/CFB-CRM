import Card from '@/Components/Dashboard/Card';
import { Icon, icons, inputClass } from '@/Components/Ui';

/** Search and priority filter. Both live in the URL; the board reloads server-side. */
export default function Toolbar({ filters, options, search, onSearch, onFilter, onClear, hasFilters, note }) {
    return (
        <Card as="div" className="p-4!">
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative min-w-52 flex-1 sm:max-w-sm">
                    <label htmlFor="pipeline-search" className="sr-only">
                        Search the pipeline by name, company or email
                    </label>
                    <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                        <Icon path={icons.search} />
                    </span>
                    <input
                        id="pipeline-search"
                        type="search"
                        value={search}
                        onChange={(e) => onSearch(e.target.value)}
                        placeholder="Search name, company or email"
                        className={`${inputClass()} py-2 ps-10`}
                    />
                </div>

                <div>
                    <label htmlFor="pipeline-priority" className="sr-only">
                        Priority
                    </label>
                    <select
                        id="pipeline-priority"
                        value={filters.priority ?? ''}
                        onChange={(e) => onFilter({ priority: e.target.value || null })}
                        className={`${inputClass()} py-2 pe-8`}
                    >
                        <option value="">All priorities</option>
                        {options.priorities.map((o) => (
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

                {note && (
                    <p className="ms-auto text-xs text-gray-500" aria-live="polite">
                        {note}
                    </p>
                )}
            </div>
        </Card>
    );
}
