import { DateFilter, SelectFilter } from '@/Components/Saims/FilterFields';
import { DateText, Money, Row, Section } from '@/Components/Saims/Detail';
import SaimsDrawer from '@/Components/Saims/SaimsDrawer';
import SaimsListPage from '@/Components/Saims/SaimsListPage';
import SaimsStatusPill from '@/Components/Saims/SaimsStatusPill';
import useListQuery from '@/Hooks/useListQuery';
import { formatNumber, formatPhp } from '@/Utils/format';
import { Link, router } from '@inertiajs/react';

const statusLabels = {
    open: 'Open',
    partially_delivered: 'Partially delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

function OrderDetails({ order }) {
    const ordered = order.items.reduce((sum, item) => sum + item.qty, 0);
    const delivered = order.items.reduce((sum, item) => sum + item.delivered_qty, 0);
    const percent = ordered > 0 ? Math.min(100, Math.round((delivered / ordered) * 100)) : 0;

    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                <SaimsStatusPill status={order.status} />
            </div>

            <p className="mt-4 text-3xl font-bold tracking-tight text-gray-900">{formatPhp(order.total)}</p>
            <p className="text-xs text-gray-500">Order total (ordered quantity × price)</p>

            <dl className="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                <Row label="Order no.">{order.so_no}</Row>
                <Row label="Customer">{order.customer?.name}</Row>
                <Row label="Order date">
                    <DateText value={order.order_date} />
                </Row>
                <Row label="Customer PO no.">{order.customer_po_no}</Row>
                <Row label="Customer PO date">
                    <DateText value={order.customer_po_date} />
                </Row>
                <Row label="Notes">{order.notes}</Row>
            </dl>

            <Section title="Lines">
                <div className="mb-3">
                    <div className="flex justify-between text-xs text-gray-500">
                        <span>{percent}% delivered</span>
                        <span>
                            {formatNumber(delivered)} of {formatNumber(ordered)}
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label="Delivered quantity"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percent}
                        className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"
                    >
                        <div className="h-full rounded-full bg-linear-to-r from-sky-400 to-primary-600" style={{ width: `${percent}%` }} />
                    </div>
                </div>

                {order.items.length === 0 ? (
                    <p className="text-sm text-gray-400">This order has no lines.</p>
                ) : (
                    <div className="-mx-1 overflow-x-auto">
                        <table className="w-full min-w-[30rem] text-sm">
                            <caption className="sr-only">Order lines</caption>
                            <thead>
                                <tr className="text-[11px] uppercase tracking-wider text-gray-400">
                                    <th scope="col" className="px-1 pb-2 text-start font-semibold">Item</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Ordered</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Delivered</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Remaining</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Price</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-1 py-2.5 text-gray-900">{item.description ?? '—'}</td>
                                        <td className="px-1 py-2.5 text-end">{formatNumber(item.qty)}</td>
                                        <td className="px-1 py-2.5 text-end">{formatNumber(item.delivered_qty)}</td>
                                        <td className={`px-1 py-2.5 text-end ${item.remaining_qty > 0 ? 'font-semibold text-amber-700' : ''}`}>
                                            {formatNumber(item.remaining_qty)}
                                        </td>
                                        <td className="whitespace-nowrap px-1 py-2.5 text-end">
                                            <Money value={item.price} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Section>
        </div>
    );
}

export default function Index({ records, filters, error, drawer, statuses, customers }) {
    const query = useListQuery('saims.orders.index', filters);

    const filterFields = (
        <>
            {customers.length > 0 && (
                <SelectFilter
                    id="filter-customer"
                    label="Customer"
                    value={filters.customer_id}
                    onChange={(value) => query.go({ customer_id: value ? Number(value) : null })}
                >
                    <option value="">All customers</option>
                    {customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>
                            {customer.name}
                        </option>
                    ))}
                </SelectFilter>
            )}
            <SelectFilter id="filter-status" label="Status" value={filters.status} onChange={(value) => query.go({ status: value })}>
                <option value="">All statuses</option>
                {statuses.map((status) => (
                    <option key={status} value={status}>
                        {statusLabels[status] ?? status}
                    </option>
                ))}
            </SelectFilter>
            <DateFilter id="filter-from" label="From" value={filters.from} max={filters.to} onChange={(value) => query.go({ from: value })} />
            <DateFilter id="filter-to" label="To" value={filters.to} min={filters.from} onChange={(value) => query.go({ to: value })} />
        </>
    );

    return (
        <SaimsListPage
            title="Sales Orders"
            subtitle="Orders from your SAIMS system."
            query={query}
            records={records}
            error={error}
            filters={filterFields}
            emptyText="SAIMS has no sales orders yet."
            drawer={
                <SaimsDrawer
                    drawer={drawer}
                    width="sm:max-w-2xl"
                    onClose={() => router.get(query.listUrl(), {}, { preserveState: true, preserveScroll: true })}
                    title={(o) => o.so_no ?? 'Sales order'}
                    subtitle={(o) => o.customer?.name}
                >
                    {(order) => <OrderDetails order={order} />}
                </SaimsDrawer>
            }
        >
            {records && (
                <div className="relative overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <caption className="sr-only">Sales orders</caption>
                        <thead>
                            <tr className="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                                <th scope="col" className="px-5 py-3 text-start font-semibold">Order</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Customer</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Order date</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Status</th>
                                <th scope="col" className="px-3 py-3 text-end font-semibold">Items</th>
                                <th scope="col" className="px-5 py-3 text-end font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.items.map((order) => (
                                <tr key={order.id} className="hover:bg-slate-50/70">
                                    <td className="px-5 py-3">
                                        <Link
                                            href={query.detailUrl('saims.orders.show', order.id)}
                                            preserveState
                                            preserveScroll
                                            className="rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            {order.so_no ?? `#${order.id}`}
                                        </Link>
                                        {order.customer_po_no && <p className="text-xs text-gray-500">PO {order.customer_po_no}</p>}
                                    </td>
                                    <td className="px-3 py-3 text-gray-700">{order.customer?.name ?? '—'}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-gray-700">
                                        <DateText value={order.order_date} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <SaimsStatusPill status={order.status} />
                                    </td>
                                    <td className="px-3 py-3 text-end text-gray-700">{order.items.length}</td>
                                    <td className="whitespace-nowrap px-5 py-3 text-end font-semibold text-gray-900">{formatPhp(order.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </SaimsListPage>
    );
}
