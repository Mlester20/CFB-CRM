const styles = {
    open: 'bg-sky-50 text-sky-700 ring-sky-200',
    partially_delivered: 'bg-amber-50 text-amber-800 ring-amber-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const labels = {
    open: 'Open',
    partially_delivered: 'Partially delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

/** Sales order status. An unknown status from SAIMS still shows, in neutral gray. */
export default function SaimsStatusPill({ status }) {
    return (
        <span
            className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                styles[status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
            }`}
        >
            {labels[status] ?? status ?? 'Unknown'}
        </span>
    );
}
