import Avatar from '@/Components/Dashboard/Avatar';
import { DateText, Row } from '@/Components/Saims/Detail';
import SaimsDrawer from '@/Components/Saims/SaimsDrawer';
import SaimsListPage from '@/Components/Saims/SaimsListPage';
import useListQuery from '@/Hooks/useListQuery';
import { formatRelative } from '@/Utils/format';
import { Link, router } from '@inertiajs/react';

function CustomerDetails({ customer }) {
    return (
        <dl className="divide-y divide-slate-100">
            <Row label="Customer">{customer.customer_name}</Row>
            <Row label="Type">{customer.customer_type}</Row>
            <Row label="Contact person">{customer.contact_person}</Row>
            <Row label="Contact number">
                {customer.contact_number && (
                    <a href={`tel:${customer.contact_number.replace(/\s/g, '')}`} className="text-primary-700 hover:underline">
                        {customer.contact_number}
                    </a>
                )}
            </Row>
            <Row label="Email">
                {customer.email && (
                    <a href={`mailto:${customer.email}`} className="text-primary-700 hover:underline">
                        {customer.email}
                    </a>
                )}
            </Row>
            <Row label="Delivery address">{customer.delivery_address}</Row>
            <Row label="Price level">{customer.price_level}</Row>
            <Row label="VAT type">{customer.vat_type}</Row>
            <Row label="Updated in SAIMS">
                <DateText value={customer.updated_at} />
            </Row>
        </dl>
    );
}

export default function Index({ records, filters, error, drawer }) {
    const query = useListQuery('saims.customers.index', filters);

    return (
        <SaimsListPage
            title="Customers"
            subtitle="Customers from your SAIMS system."
            query={query}
            records={records}
            error={error}
            searchPlaceholder="Search customers"
            emptyText="SAIMS has no customers yet."
            drawer={
                <SaimsDrawer
                    drawer={drawer}
                    onClose={() => router.get(query.listUrl(), {}, { preserveState: true, preserveScroll: true })}
                    title={(c) => c.customer_name}
                    subtitle={(c) => c.customer_type}
                >
                    {(customer) => <CustomerDetails customer={customer} />}
                </SaimsDrawer>
            }
        >
            {records && (
                <div className="relative overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-sm">
                        <caption className="sr-only">Customers</caption>
                        <thead>
                            <tr className="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                                <th scope="col" className="px-5 py-3 text-start font-semibold">Customer</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Contact</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Email</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Price level</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">VAT</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Updated</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.items.map((customer) => (
                                <tr key={customer.id} className="hover:bg-slate-50/70">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            <Avatar name={customer.customer_name ?? '?'} />
                                            <div className="min-w-0">
                                                <Link
                                                    href={query.detailUrl('saims.customers.show', customer.id)}
                                                    preserveState
                                                    preserveScroll
                                                    className="block truncate rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                                >
                                                    {customer.customer_name ?? 'Unnamed customer'}
                                                </Link>
                                                <p className="truncate text-xs text-gray-500">{customer.customer_type}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <p className="text-gray-900">{customer.contact_person ?? '—'}</p>
                                        <p className="text-xs text-gray-500">{customer.contact_number}</p>
                                    </td>
                                    <td className="px-3 py-3 text-gray-700">{customer.email ?? '—'}</td>
                                    <td className="px-3 py-3 text-gray-700">{customer.price_level ?? '—'}</td>
                                    <td className="px-3 py-3 text-gray-700">{customer.vat_type ?? '—'}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-gray-500">
                                        {customer.updated_at ? formatRelative(customer.updated_at.replace(' ', 'T')) : '—'}
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
