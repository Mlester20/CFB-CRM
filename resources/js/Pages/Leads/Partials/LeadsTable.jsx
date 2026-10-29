import Avatar from '@/Components/Dashboard/Avatar';
import { LinkedBadge, PriorityPill, SourceTag, StageBadge } from '@/Components/Leads/Pills';
import { Icon, icons } from '@/Components/Ui';
import { formatDate, formatPhp, formatRelative } from '@/Utils/format';
import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import RowMenu from './RowMenu';

function SortHeader({ label, column, filters, onSort, align = 'start' }) {
    const active = filters.sort === column;
    const direction = active ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none';

    return (
        <th scope="col" aria-sort={direction} className={`px-3 py-3 text-${align} font-semibold`}>
            <button
                type="button"
                onClick={() => onSort(column)}
                className={`inline-flex items-center gap-1 rounded-sm uppercase tracking-wider focus-visible:outline-2 focus-visible:outline-primary-600 ${
                    active ? 'text-primary-700' : 'hover:text-gray-700'
                }`}
            >
                {label}
                <span className="w-3.5" aria-hidden="true">
                    {active && (
                        <Icon
                            path={filters.direction === 'asc' ? icons.arrowUp : icons.arrowDown}
                            className="h-3.5 w-3.5"
                        />
                    )}
                </span>
                <span className="sr-only">, sort {direction === 'ascending' ? 'descending' : 'ascending'}</span>
            </button>
        </th>
    );
}

export default function LeadsTable({ leads, filters, options, urls, selection, onSort, onDelete }) {
    const sources = Object.fromEntries(options.sources.map((s) => [s.value, s.label]));
    const allRef = useRef(null);

    const allSelected = leads.length > 0 && leads.every((lead) => selection.selected.includes(lead.id));
    const someSelected = leads.some((lead) => selection.selected.includes(lead.id));

    useEffect(() => {
        if (allRef.current) {
            allRef.current.indeterminate = someSelected && !allSelected;
        }
    }, [someSelected, allSelected]);

    const checkbox = 'h-4 w-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-600';

    return (
        <div className="relative overflow-x-auto">
            <table className="w-full min-w-[56rem] text-sm">
                <caption className="sr-only">Leads</caption>
                <thead>
                    <tr className="border-b border-gray-100 text-start text-[11px] text-gray-400">
                        {selection.enabled && (
                            <th scope="col" className="w-10 ps-5 pe-1 py-3">
                                <input
                                    ref={allRef}
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={selection.toggleAll}
                                    aria-label="Select all leads on this page"
                                    className={checkbox}
                                />
                            </th>
                        )}
                        <SortHeader label="Lead" column="name" filters={filters} onSort={onSort} />
                        <th scope="col" className="px-3 py-3 text-start font-semibold uppercase tracking-wider">Stage</th>
                        <th scope="col" className="px-3 py-3 text-start font-semibold uppercase tracking-wider">Priority</th>
                        <th scope="col" className="px-3 py-3 text-start font-semibold uppercase tracking-wider">Source</th>
                        <SortHeader label="Value" column="value" filters={filters} onSort={onSort} align="end" />
                        <SortHeader label="Updated" column="updated" filters={filters} onSort={onSort} />
                        <th scope="col" className="w-12 pe-4 py-3">
                            <span className="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {leads.map((lead) => {
                        const checked = selection.selected.includes(lead.id);

                        return (
                            <tr key={lead.id} className={checked ? 'bg-primary-50/50' : 'hover:bg-slate-50/70'}>
                                {selection.enabled && (
                                    <td className="ps-5 pe-1 py-3">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => selection.toggle(lead.id)}
                                            aria-label={`Select ${lead.name}`}
                                            className={checkbox}
                                        />
                                    </td>
                                )}
                                <td className="px-3 py-3">
                                    <div className="flex items-center gap-3">
                                        <Avatar name={lead.name} />
                                        <div className="min-w-0">
                                            <Link
                                                href={urls(lead).show}
                                                preserveState
                                                preserveScroll
                                                className="block truncate rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                            >
                                                {lead.name}
                                            </Link>
                                            <p className="truncate text-xs text-gray-500">{lead.company}</p>
                                            {lead.saims_linked && (
                                                <span className="mt-1 inline-block">
                                                    <LinkedBadge />
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-3 py-3"><StageBadge stage={lead.stage} /></td>
                                <td className="px-3 py-3"><PriorityPill priority={lead.priority} /></td>
                                <td className="px-3 py-3"><SourceTag label={sources[lead.source] ?? lead.source} /></td>
                                <td className="whitespace-nowrap px-3 py-3 text-end font-semibold text-gray-900">
                                    {formatPhp(lead.value)}
                                </td>
                                <td className="whitespace-nowrap px-3 py-3 text-gray-500">
                                    <time dateTime={lead.updated_at} title={formatDate(lead.updated_at.slice(0, 10))}>
                                        {formatRelative(lead.updated_at)}
                                    </time>
                                </td>
                                <td className="pe-4 py-3 text-end">
                                    <RowMenu lead={lead} urls={urls(lead)} onDelete={() => onDelete(lead)} />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
