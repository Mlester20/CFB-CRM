import { DateText, Row } from '@/Components/Saims/Detail';
import SaimsStatusPill from '@/Components/Saims/SaimsStatusPill';
import { Badge, Icon, icons } from '@/Components/Ui';
import { formatNumber, formatPhp } from '@/Utils/format';
import { useEffect, useState } from 'react';

function Skeleton() {
    return (
        <div role="status" aria-busy="true" className="space-y-3">
            <span className="sr-only">Loading SAIMS data…</span>
            {[0, 1, 2].map((i) => (
                <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-100" aria-hidden="true" />
            ))}
        </div>
    );
}

function BlockError({ error }) {
    return (
        <p role="alert" className="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <Icon path={icons.warning2} className="mt-0.5 h-4 w-4 shrink-0" />
            {error.message}
        </p>
    );
}

function Block({ title, children }) {
    return (
        <div className="mt-5">
            <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-400">{title}</h4>
            <div className="mt-2">{children}</div>
        </div>
    );
}

function CustomerCard({ customer }) {
    return (
        <div className="rounded-xl bg-slate-50 px-4">
            <dl className="divide-y divide-slate-100">
                <Row label="Customer">{customer.customer_name}</Row>
                <Row label="Type">{customer.customer_type}</Row>
                <Row label="Contact">{[customer.contact_person, customer.contact_number].filter(Boolean).join(' · ') || null}</Row>
                <Row label="Delivery">{customer.delivery_address}</Row>
                <Row label="Price level">{customer.price_level}</Row>
            </dl>
        </div>
    );
}

function Orders({ orders }) {
    if (orders.items.length === 0) return <p className="text-sm text-gray-500">No sales orders for this customer yet.</p>;

    return (
        <ul className="divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
            {orders.items.map((order) => {
                const percent = order.ordered > 0 ? Math.min(100, Math.round((order.delivered / order.ordered) * 100)) : 0;

                return (
                    <li key={order.id} className="py-3">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-semibold text-gray-900">{order.so_no ?? `#${order.id}`}</p>
                            <SaimsStatusPill status={order.status} />
                        </div>
                        <p className="mt-0.5 text-xs text-gray-500">
                            <DateText value={order.order_date} /> · delivered {formatNumber(order.delivered)} of {formatNumber(order.ordered)}
                        </p>
                        <div
                            role="progressbar"
                            aria-label={`${order.so_no} delivered`}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={percent}
                            className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200"
                        >
                            <div className="h-full rounded-full bg-primary-600" style={{ width: `${percent}%` }} />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function Invoices({ invoices }) {
    return (
        <>
            <div className="grid grid-cols-2 gap-3">
                <div className="rounded-xl bg-primary-50 p-3">
                    <p className="text-xs font-medium text-primary-700">Outstanding</p>
                    <p className="mt-0.5 text-lg font-bold text-primary-900">{formatPhp(invoices.outstanding)}</p>
                </div>
                <div className="rounded-xl bg-emerald-50 p-3">
                    <p className="text-xs font-medium text-emerald-700">Paid</p>
                    <p className="mt-0.5 text-lg font-bold text-emerald-900">{formatPhp(invoices.paid)}</p>
                </div>
            </div>
            {invoices.partial && (
                <p className="mt-2 text-xs text-gray-500">
                    Based on the latest invoices only ({formatNumber(invoices.total)} in total).
                </p>
            )}

            {invoices.items.length === 0 ? (
                <p className="mt-3 text-sm text-gray-500">No invoices for this customer yet.</p>
            ) : (
                <ul className="mt-3 divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
                    {invoices.items.map((invoice) => (
                        <li key={invoice.id} className="py-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-gray-900">
                                    {invoice.sales_no ?? `#${invoice.id}`}
                                    {invoice.cancelled && (
                                        <span className="ms-2 align-middle">
                                            <Badge tone="red">Cancelled</Badge>
                                        </span>
                                    )}
                                </p>
                                <p className="text-sm font-semibold text-gray-900">{formatPhp(invoice.total_sales)}</p>
                            </div>
                            <p className="mt-0.5 text-xs text-gray-500">
                                <DateText value={invoice.date} /> · due {formatPhp(invoice.amount_due)} · paid {formatPhp(invoice.amount_paid)}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

/**
 * The SAIMS section of the lead drawer. It loads after the drawer has opened,
 * from a separate endpoint, and only the blocks the user may see arrive.
 * A failing block shows its own friendly message; the rest keeps working.
 */
export default function LeadSaimsPanel({ lead }) {
    const [state, setState] = useState({ status: 'loading', blocks: {} });

    useEffect(() => {
        const controller = new AbortController();

        setState({ status: 'loading', blocks: {} });

        fetch(route('leads.saims-panel', lead.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));

                return response.json();
            })
            .then((json) => setState({ status: 'ready', blocks: json.blocks ?? {} }))
            .catch((e) => {
                if (e.name !== 'AbortError') setState({ status: 'failed', blocks: {} });
            });

        return () => controller.abort();
    }, [lead.id]);

    const { blocks } = state;

    return (
        <section aria-labelledby="saims-panel-title" className="mt-6 border-t border-slate-100 pt-5">
            <h3 id="saims-panel-title" className="flex items-center gap-2 text-sm font-semibold text-gray-900">
                <Icon path={icons.link} className="h-4 w-4 text-sky-600" />
                SAIMS
                <Badge tone="blue">Read-only</Badge>
            </h3>

            {state.status === 'loading' && (
                <div className="mt-3">
                    <Skeleton />
                </div>
            )}

            {state.status === 'failed' && (
                <div className="mt-3">
                    <BlockError error={{ message: 'The SAIMS information could not be loaded right now.' }} />
                </div>
            )}

            {state.status === 'ready' && (
                <>
                    {blocks.customer && (
                        <Block title="Customer">
                            {blocks.customer.error ? <BlockError error={blocks.customer.error} /> : <CustomerCard customer={blocks.customer.data} />}
                        </Block>
                    )}
                    {blocks.orders && (
                        <Block title="Latest sales orders">
                            {blocks.orders.error ? <BlockError error={blocks.orders.error} /> : <Orders orders={blocks.orders.data} />}
                        </Block>
                    )}
                    {blocks.invoices && (
                        <Block title="Invoices">
                            {blocks.invoices.error ? <BlockError error={blocks.invoices.error} /> : <Invoices invoices={blocks.invoices.data} />}
                        </Block>
                    )}
                </>
            )}
        </section>
    );
}
