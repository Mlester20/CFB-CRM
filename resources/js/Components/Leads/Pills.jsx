import StatusPill from '@/Components/Dashboard/StatusPill';
import { Icon, icons } from '@/Components/Ui';

const cap = (value) => value.charAt(0).toUpperCase() + value.slice(1);

/** Stage pill. The stage comes as 'new', 'qualified', ... and shares the dashboard colors. */
export function StageBadge({ stage }) {
    return <StatusPill status={cap(stage)} />;
}

const priorityStyles = {
    high: 'bg-rose-50 text-rose-700 ring-rose-200',
    medium: 'bg-amber-50 text-amber-800 ring-amber-200',
    low: 'bg-sky-50 text-sky-700 ring-sky-200',
};

export function PriorityPill({ priority }) {
    return (
        <span
            className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                priorityStyles[priority] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
            }`}
        >
            <span className="sr-only">Priority: </span>
            {cap(priority)}
        </span>
    );
}

/** Neutral tag for the lead source. `label` comes from the server options. */
export function SourceTag({ label }) {
    return (
        <span className="inline-flex items-center whitespace-nowrap rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
            {label}
        </span>
    );
}

/** Small badge for a lead that is linked to a SAIMS customer (a local column: no SAIMS call). */
export function LinkedBadge() {
    return (
        <span
            title="Linked to a SAIMS customer"
            className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200 ring-inset"
        >
            <Icon path={icons.link} className="h-3 w-3" />
            Linked
        </span>
    );
}
