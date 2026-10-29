import { Badge } from '@/Components/Ui';
import { DateText, Money, Row, Section } from '@/Components/Saims/Detail';
import SaimsDrawer from '@/Components/Saims/SaimsDrawer';
import SaimsListPage from '@/Components/Saims/SaimsListPage';
import useListQuery from '@/Hooks/useListQuery';
import { formatNumber } from '@/Utils/format';
import { Link, router } from '@inertiajs/react';

/** Low stock (at or below the threshold) or out of stock. Nothing when stock is fine. */
function StockBadge({ product }) {
    if (product.out_of_stock) return <Badge tone="red">Out of stock</Badge>;
    if (product.low_stock) return <Badge tone="amber">Low stock</Badge>;

    return null;
}

function Locations({ stock }) {
    if (stock.by_location.length === 0) return <span className="text-xs text-gray-400">No stock recorded</span>;

    return (
        <span className="text-xs text-gray-500">
            {stock.by_location.map((l) => `${l.name} ${formatNumber(l.qty)}`).join(' · ')}
        </span>
    );
}

function ProductDetails({ product }) {
    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                <StockBadge product={product} />
            </div>

            <dl className="divide-y divide-slate-100">
                <Row label="Code">{product.code}</Row>
                <Row label="Item">{product.item_name}</Row>
                <Row label="Brand">{product.brand_name}</Row>
                <Row label="Generic name">{product.generic_name}</Row>
                <Row label="Barcode">{product.barcode}</Row>
                <Row label="Unit">{product.unit}</Row>
                <Row label="FDA reg. no.">{product.fda_reg_no}</Row>
                <Row label="Description">{product.description}</Row>
                <Row label="Updated in SAIMS">
                    <DateText value={product.updated_at} />
                </Row>
            </dl>

            <Section title="Prices">
                <dl className="divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
                    {[
                        ['Retail', 'retail'],
                        ['Wholesale', 'wholesale'],
                        ['Level 1', 'level_1'],
                        ['Level 2', 'level_2'],
                        ['Level 3', 'level_3'],
                    ].map(([label, key]) => (
                        <div key={key} className="flex items-center justify-between py-2.5 text-sm">
                            <dt className="text-gray-600">{label}</dt>
                            <dd className="font-semibold text-gray-900">
                                <Money value={product.prices[key]} />
                            </dd>
                        </div>
                    ))}
                </dl>
            </Section>

            <Section title="Stock">
                <p className="text-2xl font-bold text-gray-900">
                    {formatNumber(product.stock.total)} <span className="text-sm font-medium text-gray-500">{product.unit}</span>
                </p>
                {product.low_stock_threshold !== null && (
                    <p className="mt-1 text-xs text-gray-500">Low-stock threshold: {formatNumber(product.low_stock_threshold)}</p>
                )}
                {product.stock.by_location.length > 0 ? (
                    <ul className="mt-3 divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
                        {product.stock.by_location.map((location) => (
                            <li key={location.name} className="flex items-center justify-between py-2.5 text-sm">
                                <span className="text-gray-600">{location.name}</span>
                                <span className="font-semibold text-gray-900">{formatNumber(location.qty)}</span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-2 text-sm text-gray-400">No stock recorded in any location.</p>
                )}
            </Section>
        </div>
    );
}

export default function Index({ records, filters, error, drawer }) {
    const query = useListQuery('saims.products.index', filters);

    return (
        <SaimsListPage
            title="Products"
            subtitle="Prices and stock from your SAIMS system."
            query={query}
            records={records}
            error={error}
            searchPlaceholder="Search name, code or barcode"
            emptyText="SAIMS has no products yet."
            drawer={
                <SaimsDrawer
                    drawer={drawer}
                    onClose={() => router.get(query.listUrl(), {}, { preserveState: true, preserveScroll: true })}
                    title={(p) => p.item_name ?? 'Product'}
                    subtitle={(p) => p.code}
                >
                    {(product) => <ProductDetails product={product} />}
                </SaimsDrawer>
            }
        >
            {records && (
                <div className="relative overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <caption className="sr-only">Products</caption>
                        <thead>
                            <tr className="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                                <th scope="col" className="px-5 py-3 text-start font-semibold">Product</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Unit</th>
                                <th scope="col" className="px-3 py-3 text-end font-semibold">Retail</th>
                                <th scope="col" className="px-3 py-3 text-end font-semibold">Wholesale</th>
                                <th scope="col" className="px-3 py-3 text-start font-semibold">Stock</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {records.items.map((product) => (
                                <tr key={product.id} className="hover:bg-slate-50/70">
                                    <td className="px-5 py-3">
                                        <Link
                                            href={query.detailUrl('saims.products.show', product.id)}
                                            preserveState
                                            preserveScroll
                                            className="block truncate rounded-sm font-semibold text-gray-900 hover:text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            {product.item_name ?? 'Unnamed product'}
                                        </Link>
                                        <p className="truncate text-xs text-gray-500">
                                            {[product.code, product.brand_name ?? product.generic_name].filter(Boolean).join(' · ')}
                                        </p>
                                        {/* Small screens: the price and stock columns are off to the right, so repeat them here. */}
                                        <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-700 sm:hidden">
                                            <span className="font-semibold">
                                                <Money value={product.prices.retail} />
                                            </span>
                                            <span>Stock {formatNumber(product.stock.total)}</span>
                                            <StockBadge product={product} />
                                        </p>
                                    </td>
                                    <td className="px-3 py-3 text-gray-700">{product.unit ?? '—'}</td>
                                    <td className="whitespace-nowrap px-3 py-3 text-end font-semibold text-gray-900">
                                        <Money value={product.prices.retail} />
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-3 text-end text-gray-700">
                                        <Money value={product.prices.wholesale} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold text-gray-900">{formatNumber(product.stock.total)}</span>
                                            <StockBadge product={product} />
                                        </div>
                                        <Locations stock={product.stock} />
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
