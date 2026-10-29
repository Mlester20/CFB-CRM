import { LinkedBadge, PriorityPill, SourceTag, StageBadge } from '@/Components/Leads/Pills';
import LeadSaimsPanel from './LeadSaimsPanel';
import { formatDate, formatPhp, formatRelative } from '@/Utils/format';

function Row({ label, children }) {
    return (
        <div className="grid grid-cols-3 gap-3 py-3">
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="col-span-2 min-w-0 break-words text-sm text-gray-900">{children ?? <span className="text-gray-400">Not set</span>}</dd>
        </div>
    );
}

export default function LeadDetails({ lead, options }) {
    const source = options.sources.find((s) => s.value === lead.source)?.label ?? lead.source;

    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                <StageBadge stage={lead.stage} />
                <PriorityPill priority={lead.priority} />
                <SourceTag label={source} />
            </div>

            <p className="mt-5 text-3xl font-bold tracking-tight text-gray-900">{formatPhp(lead.value)}</p>
            <p className="text-xs text-gray-500">Deal value</p>

            <dl className="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                <Row label="Company">{lead.company}</Row>
                <Row label="Email">
                    {lead.email && (
                        <a href={`mailto:${lead.email}`} className="text-primary-700 hover:underline">
                            {lead.email}
                        </a>
                    )}
                </Row>
                <Row label="Phone">
                    {lead.phone && (
                        <a href={`tel:${lead.phone.replace(/\s/g, '')}`} className="text-primary-700 hover:underline">
                            {lead.phone}
                        </a>
                    )}
                </Row>
                <Row label="Assigned to">{lead.owner?.name}</Row>
                {lead.saims_customer !== undefined && (
                    <Row label="SAIMS customer">
                        {lead.saims_customer && (
                            <span className="inline-flex flex-wrap items-center gap-2">
                                {lead.saims_customer.name}
                                <LinkedBadge />
                            </span>
                        )}
                    </Row>
                )}
                <Row label="Created">{lead.created_at && formatDate(lead.created_at.slice(0, 10))}</Row>
                <Row label="Updated">
                    {lead.updated_at && (
                        <>
                            {formatDate(lead.updated_at.slice(0, 10))}{' '}
                            <span className="text-gray-500">({formatRelative(lead.updated_at)})</span>
                        </>
                    )}
                </Row>
            </dl>

            {lead.saims_linked && <LeadSaimsPanel lead={lead} />}

            <div className="mt-5 border-t border-slate-100 pt-5">
                <h3 className="text-sm font-medium text-gray-500">Notes</h3>
                {lead.notes ? (
                    <p className="mt-2 whitespace-pre-wrap break-words text-sm text-gray-900">{lead.notes}</p>
                ) : (
                    <p className="mt-2 text-sm text-gray-400">No notes yet.</p>
                )}
            </div>
        </div>
    );
}
