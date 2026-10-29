import { DateFilter, SelectFilter } from '@/Components/Saims/FilterFields';
import { DateText, Money, Row, Section } from '@/Components/Saims/Detail';
import SaimsDrawer from '@/Components/Saims/SaimsDrawer';
import SaimsListPage from '@/Components/Saims/SaimsListPage';
import { Badge } from '@/Components/Ui';
import useListQuery from '@/Hooks/useListQuery';
import { formatNumber, formatPhp } from '@/Utils/format';
import { Link, router } from '@inertiajs/react';

const breakdown = [
    ['VAT sales', 'vat_sales'],
    ['VAT-exempt sales', 'vat_exempt_sales'],
    ['Zero-rated sales', 'zero_rated_sales'],
    ['VAT amount', 'vat_amount'],
    ['Total sales', 'total_sales', true],
    ['Less: VAT', 'less_vat'],
    ['Less: Senior / PWD discount', 'less_senior_pwd'],
    ['Less: Withholding tax', 'less_withholding_tax'],
    ['Add: VAT', 'add_vat'],
    ['Net amount', 'amount_net', true],
];

function InvoiceDetails({ invoice }) {
    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                {invoice.cancelled && <Badge tone="red">Cancelled</Badge>}
            </div>

            <div className="mt-4 grid grid-cols-2 gap-3">
                <div className="rounded-2xl bg-primary-50 p-4">
                    <p className="text-xs font-medium text-primary-700">Amount due</p>
                    <p className="mt-1 text-xl font-bold text-primary-900">{formatPhp(invoice.totals.amount_due)}</p>
                </div>
                <div className="rounded-2xl bg-emerald-50 p-4">
                    <p className="text-xs font-medium text-emerald-700">Amount paid</p>
                    <p className="mt-1 text-xl font-bold text-emerald-900">{formatPhp(invoice.totals.amount_paid)}</p>
                </div>
            </div>

            <dl className="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                <Row label="Invoice no.">{invoice.sales_no}</Row>
                <Row label="Customer">{invoice.customer?.name}</Row>
                <Row label="Date">
                    <DateText value={invoice.date} />
                </Row>
                <Row label="Customer PO no.">{invoice.customer_po_no}</Row>
            </dl>

            <Section title="Totals">
                <dl className="divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
                    {breakdown.map(([label, key, strong]) => (
                        <div key={key} className="flex items-center justify-between py-2.5 text-sm">
                            <dt className={strong ? 'font-semibold text-gray-900' : 'text-gray-600'}>{label}</dt>
                            <dd className={strong ? 'font-bold text-gray-900' : 'text-gray-900'}>{formatPhp(invoice.totals[key])}</dd>
                        </div>
                    ))}
                </dl>
            </Section>

            <Section title="Lines">
                {invoice.lines.length === 0 ? (
                    <p className="text-sm text-gray-400">This invoice has no lines.</p>
                ) : (
                    <div className="-mx-1 overflow-x-auto">
                        <table className="w-full min-w-[34rem] text-sm">
                            <caption className="sr-only">Invoice lines</caption>
                            <thead>
                                <tr className="text-[11px] uppercase tracking-wider text-gray-400">
                                    <th scope="col" className="px-1 pb-2 text-start font-semibold">Item</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Qty</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Price</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Discount</th>
                                    <th scope="col" className="px-1 pb-2 text-end font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {invoice.lines.map((line, index) => (
                                    <tr key={index}>
                                        <td className="px-1 py-2.5">
                                            <p className="text-gray-900">{line.description ?? '—'}</p>
                                            <p className="text-xs text-gray-500">
                                                {[line.batch_no && `Batch ${line.batch_no}`, line.expiration_date && `Exp ${line.expiration_date}`]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </td>
                                        <td className="whitespace-nowrap px-1 py-2.5 text-end">
                                            {formatNumber(line.qty)} {line.unit}
                                        </td>
                                        <td className="whitespace-nowrap px-1 py-2.5 text-end"><Money value={line.price} /></td>
                                        <td className="whitespace-nowrap px-1 py-2.5 text-end"><Money value={line.discount} /></td>
                                        <td className="whitespace-nowrap px-1 py-2.5 text-end font-semibold"><Money value={line.amount} /></td>
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

export default function Index({ records, filters, error, drawer, customers }) {
    const query = useListQuery('saims.invoices.index', filters);

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
            <DateFilter id="filter-from" label="From" value={filters.from} max={filters.to} onChange={(value) => query.go({ from: value })} />
            <DateFilter id="filter-to" label="To" value={filters.to} min={filters.from} onChange={(value) => query.go({ to: value })} />
        </>
    );

    return (
        <SaimsListPage
            title="Invoices"
            subtitle="Sales invoices from your SAIMS system."
            query={query}
            records={records}
            error={error}
            filters={filterFields}
            emptyText="SAIMS has no invoices yet."
            drawer={
                <SaimsDrawer
                    drawer={drawer}
                    width="sm:max-w-2xl"
                    onClose={() => router.get(query.listUrl(), {}, { preserveState: true, preserveScroll: true })}
                    title={(i) => i.sales_no ?? 'Invoice'}
                    subtitle={(i) => i.customer?.name}
                >
                    {(invoice) => <InvoiceDetails invoice={invoice} />}
                </SaimsDrawer>
            }
        >
            {records && (
                <div className="relative overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <caption className="sr-only">Invoices</caption>
                        <thead>
                            <tr className="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                                <th scope="col" className="px-5 py-3 text-start font-semibold">Invoice</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Customer</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Date</th>
                                <th scope="col" className="px-3 py-3 text-end font-semibold">Total sales</th>
                                <th scope="col" className="px-3 py-3 text-end font-semibold">Amount paid</th>
                                <th scope="col" className="px-5 py-3 text-end font-semibold">Amount due</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.items.map((invoice) => (
                                <tr key={invoice.id} className="hover:bg-slate-50/70">
                                    <td className="px-5 py-3">
                                        <Link
                                            href={query.detailUrl('saims.invoices.show', invoice.id)}
                                            preserveState
                                            preserveScroll
                                            className="rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            {invoice.sales_no ?? `#${invoice.id}`}
                                        </Link>
                                        {invoice.cancelled && (
                                            <span className="ms-2 align-middle">
                                                <Badge tone="red">Cancelled</Badge>
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-gray-700">{invoice.customer?.name ?? '—'}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-gray-700">
                                        <DateText value={invoice.date} />
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-3 text-end text-gray-700">{formatPhp(invoice.totals.total_sales)}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-end text-gray-700">{formatPhp(invoice.totals.amount_paid)}</td>
                                    <td className="whitespace-nowrap px-5 py-3 text-end font-semibold text-gray-900">
                                        {formatPhp(invoice.totals.amount_due)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </SaimsListPage>
    );
}
