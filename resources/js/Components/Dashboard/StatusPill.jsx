const styles = {
    New: 'bg-sky-50 text-sky-700 ring-sky-200',
    Qualified: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    Proposal: 'bg-amber-50 text-amber-800 ring-amber-200',
    Won: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Lost: 'bg-rose-50 text-rose-700 ring-rose-200',
};

export default function StatusPill({ status }) {
    return (
        <span
            className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                styles[status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
            }`}
        >
            {status}
        </span>
    );
}
