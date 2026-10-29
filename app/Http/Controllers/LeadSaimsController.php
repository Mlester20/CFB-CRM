<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\SearchSaimsCustomersRequest;
use App\Models\Lead;
use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The two small JSON endpoints behind the lead drawer and the lead form:
 *  - the "SAIMS customer" picker search, and
 *  - the lazy-loaded SAIMS panel of a linked lead.
 *
 * Both call only fixed SAIMS endpoints through SaimsService, and each SAIMS
 * block is fetched only when the user has that block's permission.
 */
class LeadSaimsController extends Controller
{
    /** The picker never shows more than this many customers. */
    private const PICKER_LIMIT = 10;

    /** Latest records shown in the panel. */
    private const PANEL_ROWS = 5;

    /** Invoices read to total Outstanding and Paid (SAIMS has no totals endpoint). */
    private const INVOICE_WINDOW = 100;

    public function customers(SearchSaimsCustomersRequest $request, SaimsService $saims): JsonResponse
    {
        try {
            $list = $saims->customers([
                'search' => $request->validated('search') ?? '',
                'per_page' => self::PICKER_LIMIT,
            ]);
        } catch (SaimsException $e) {
            return response()->json(['items' => [], 'error' => $e->toArray()]);
        }

        return response()->json([
            'items' => array_map(
                fn (array $c) => ['id' => $c['id'], 'name' => $c['customer_name'], 'type' => $c['customer_type']],
                array_slice($list['items'], 0, self::PICKER_LIMIT),
            ),
            'error' => null,
        ]);
    }

    public function panel(Request $request, SaimsService $saims, string $lead): JsonResponse
    {
        $user = $request->user();

        Gate::authorize('viewAny', Lead::class);
        $lead = Lead::findOrFail($lead);
        Gate::authorize('view', $lead);

        $canCustomers = $user->can('saims.customers');
        $canOrders = $user->can('saims.orders');
        $canInvoices = $user->can('saims.invoices');

        abort_unless($canCustomers || $canOrders || $canInvoices, 403);

        if ($lead->saims_customer_id === null) {
            return response()->json(['linked' => false, 'blocks' => (object) []]);
        }

        $id = (int) $lead->saims_customer_id;
        $blocks = [];

        // Each block is fetched only with its own permission and fails on its own.
        if ($canCustomers) {
            $blocks['customer'] = $this->block(fn () => $this->customerCard($saims->customerById($id)));
        }

        if ($canOrders) {
            $blocks['orders'] = $this->block(fn () => $this->orders($saims, $id));
        }

        if ($canInvoices) {
            $blocks['invoices'] = $this->block(fn () => $this->invoices($saims, $id));
        }

        return response()->json(['linked' => true, 'blocks' => $blocks]);
    }

    /**
     * @param  callable(): array<string, mixed>  $load
     * @return array{data: array<string, mixed>|null, error: array{code: string, message: string}|null}
     */
    private function block(callable $load): array
    {
        try {
            return ['data' => $load(), 'error' => null];
        } catch (SaimsException $e) {
            return ['data' => null, 'error' => $e->toArray()];
        }
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function customerCard(array $customer): array
    {
        return [
            'customer_name' => $customer['customer_name'],
            'customer_type' => $customer['customer_type'],
            'contact_person' => $customer['contact_person'],
            'contact_number' => $customer['contact_number'],
            'delivery_address' => $customer['delivery_address'],
            'price_level' => $customer['price_level'],
        ];
    }

    /** @return array<string, mixed> */
    private function orders(SaimsService $saims, int $customerId): array
    {
        $list = $saims->salesOrders(['customer_id' => $customerId, 'per_page' => self::PANEL_ROWS]);

        return [
            'total' => $list['meta']['total'],
            'items' => array_map(fn (array $order) => [
                'id' => $order['id'],
                'so_no' => $order['so_no'],
                'order_date' => $order['order_date'],
                'status' => $order['status'],
                'ordered' => array_sum(array_column($order['items'], 'qty')),
                'delivered' => array_sum(array_column($order['items'], 'delivered_qty')),
            ], array_slice($list['items'], 0, self::PANEL_ROWS)),
        ];
    }

    /** @return array<string, mixed> */
    private function invoices(SaimsService $saims, int $customerId): array
    {
        $list = $saims->invoices(['customer_id' => $customerId, 'per_page' => self::INVOICE_WINDOW]);
        $counted = array_filter($list['items'], fn (array $invoice) => ! $invoice['cancelled']);

        return [
            'total' => $list['meta']['total'],
            // Totals over the latest invoices read (cancelled ones left out).
            'outstanding' => round(array_sum(array_map(fn ($i) => $i['totals']['amount_due'], $counted)), 2),
            'paid' => round(array_sum(array_map(fn ($i) => $i['totals']['amount_paid'], $counted)), 2),
            'partial' => $list['meta']['total'] > count($list['items']),
            'items' => array_map(fn (array $invoice) => [
                'id' => $invoice['id'],
                'sales_no' => $invoice['sales_no'],
                'date' => $invoice['date'],
                'cancelled' => $invoice['cancelled'],
                'total_sales' => $invoice['totals']['total_sales'],
                'amount_due' => $invoice['totals']['amount_due'],
                'amount_paid' => $invoice['totals']['amount_paid'],
            ], array_slice($list['items'], 0, self::PANEL_ROWS)),
        ];
    }
}
