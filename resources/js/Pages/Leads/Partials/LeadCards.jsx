import Avatar from '@/Components/Dashboard/Avatar';
import { LinkedBadge, PriorityPill, SourceTag, StageBadge } from '@/Components/Leads/Pills';
import { formatDate, formatPhp, formatRelative } from '@/Utils/format';
import { Link } from '@inertiajs/react';
import RowMenu from './RowMenu';

export default function LeadCards({ leads, options, urls, selection, onDelete }) {
    const sources = Object.fromEntries(options.sources.map((s) => [s.value, s.label]));

    return (
        <ul className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
            {leads.map((lead) => {
                const checked = selection.selected.includes(lead.id);

                return (
                    <li
                        key={lead.id}
                        className={`flex flex-col rounded-2xl p-4 ring-1 transition ${
                            checked ? 'bg-primary-50/50 ring-primary-200' : 'bg-white ring-slate-200 hover:shadow-md'
                        }`}
                    >
                        <div className="flex items-start gap-3">
                            {selection.enabled && (
                                <input
                                    type="checkbox"
                                    checked={checked}
                                    onChange={() => selection.toggle(lead.id)}
                                    aria-label={`Select ${lead.name}`}
                                    className="mt-3 h-4 w-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-600"
                                />
                            )}
                            <Avatar name={lead.name} />
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={urls(lead).show}
                                    preserveState
                                    preserveScroll
                                    className="block truncate rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    {lead.name}
                                </Link>
                                <p className="truncate text-sm text-gray-500">{lead.company}</p>
                            </div>
                            <RowMenu lead={lead} urls={urls(lead)} onDelete={() => onDelete(lead)} />
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <StageBadge stage={lead.stage} />
                            <PriorityPill priority={lead.priority} />
                            <SourceTag label={sources[lead.source] ?? lead.source} />
                            {lead.saims_linked && <LinkedBadge />}
                        </div>

                        <div className="mt-4 flex items-end justify-between gap-2 border-t border-slate-100 pt-3">
                            <p className="text-lg font-bold text-gray-900">{formatPhp(lead.value)}</p>
                            <time
                                dateTime={lead.updated_at}
                                title={formatDate(lead.updated_at.slice(0, 10))}
                                className="text-xs text-gray-500"
                            >
                                {formatRelative(lead.updated_at)}
                            </time>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
