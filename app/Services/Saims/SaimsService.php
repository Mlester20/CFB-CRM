<?php

namespace App\Services\Saims;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Clean, typed arrays from the SAIMS API for the CRM pages.
 *
 * Fixed endpoints only, and only whitelisted query parameters are forwarded:
 * the browser never chooses a URL or a path. Money arrives from SAIMS as
 * strings ("20.00") and leaves here as numbers. Everything from SAIMS is
 * treated as untrusted text: it is cast to plain strings and the UI escapes it.
 */
class SaimsService
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 15;

    /** Sales order statuses SAIMS uses, for the filter. */
    public const ORDER_STATUSES = ['open', 'partially_delivered', 'completed', 'cancelled'];

    public function __construct(private readonly SaimsClient $client) {}

    // ---------------------------------------------------------------- customers

    /**
     * @param  array<string, mixed>  $filters  search, page, per_page
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function customers(array $filters = []): array
    {
        return $this->list('/customers', $filters, ['search'], $this->customer(...));
    }

    /** @return array<string, mixed> */
    public function customerById(int $id): array
    {
        return $this->customer($this->one("/customers/{$id}"));
    }

    // ----------------------------------------------------------------- products

    /** @return array{items: list<array<string, mixed>>, meta: array<string, int>} */
    public function products(array $filters = []): array
    {
        return $this->list('/products', $filters, ['search'], $this->product(...));
    }

    /** @return array<string, mixed> */
    public function productById(int $id): array
    {
        return $this->product($this->one("/products/{$id}"));
    }

    // ------------------------------------------------------------- sales orders

    /** @return array{items: list<array<string, mixed>>, meta: array<string, int>} */
    public function salesOrders(array $filters = []): array
    {
        return $this->list('/sales-orders', $filters, ['customer_id', 'status', 'from', 'to'], $this->salesOrder(...));
    }

    /** @return array<string, mixed> */
    public function salesOrderById(int $id): array
    {
        return $this->salesOrder($this->one("/sales-orders/{$id}"));
    }

    // ----------------------------------------------------------------- invoices

    /** @return array{items: list<array<string, mixed>>, meta: array<string, int>} */
    public function invoices(array $filters = []): array
    {
        return $this->list('/invoices', $filters, ['customer_id', 'from', 'to'], $this->invoice(...));
    }

    /** @return array<string, mixed> */
    public function invoiceById(int $id): array
    {
        return $this->invoice($this->one("/invoices/{$id}"));
    }

    // --------------------------------------------------------------- connection

    /**
     * A cheap live check for the Admin "SAIMS connection" page:
     * GET /customers?per_page=1, never cached, never shows the token.
     *
     * @return array{state: string, message: string, response_ms: ?int, checked_at: string}
     */
    public function checkConnection(): array
    {
        $started = microtime(true);

        try {
            $this->client->get('/customers', ['per_page' => 1], cache: false);

            return $this->status('connected', 'SAIMS is connected and answering.', $started);
        } catch (SaimsException $e) {
            // A refused or unreachable call has no meaningful response time.
            $measured = in_array($e->failure, [SaimsFailure::Disabled, SaimsFailure::NotConfigured, SaimsFailure::Unreachable], true)
                ? null
                : $started;

            return $this->status($e->failure->value, $e->getMessage(), $measured);
        }
    }

    /**
     * The status, kept for a few seconds so refreshing the page cannot burn
     * through the 60 calls a minute.
     *
     * @return array{state: string, message: string, response_ms: ?int, checked_at: string}
     */
    public function connectionStatus(int $seconds = 10): array
    {
        return Cache::remember('saims:connection-status', $seconds, fn () => $this->checkConnection());
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  list<string>  $allowed  filters that may be forwarded to SAIMS
     * @param  callable(array<string, mixed>): array<string, mixed>  $normalize
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function list(string $path, array $filters, array $allowed, callable $normalize): array
    {
        $body = $this->client->get($path, $this->query($filters, $allowed));

        $rows = is_array($body['data'] ?? null) ? array_values($body['data']) : [];
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        $current = max(1, (int) ($meta['current_page'] ?? 1));
        $perPage = max(1, (int) ($meta['per_page'] ?? self::DEFAULT_PER_PAGE));
        $total = max(0, (int) ($meta['total'] ?? count($rows)));

        return [
            'items' => array_map(fn ($row) => $normalize(is_array($row) ? $row : []), $rows),
            'meta' => [
                'current_page' => $current,
                'last_page' => max(1, (int) ($meta['last_page'] ?? 1)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $rows === [] ? 0 : (($current - 1) * $perPage) + 1,
                'to' => $rows === [] ? 0 : (($current - 1) * $perPage) + count($rows),
            ],
        ];
    }

    /**
     * Only whitelisted, validated parameters are ever sent to SAIMS.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowed
     * @return array<string, scalar>
     */
    private function query(array $filters, array $allowed): array
    {
        $query = [
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(self::MAX_PER_PAGE, max(1, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))),
        ];

        foreach (Arr::only($filters, $allowed) as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query[$name] = match ($name) {
                'customer_id' => max(1, (int) $value),
                'status' => in_array($value, self::ORDER_STATUSES, true) ? $value : null,
                'from', 'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? (string) $value : null,
                default => mb_substr(trim((string) $value), 0, 100),
            };

            if ($query[$name] === null) {
                unset($query[$name]);
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed> the record inside {"data": {...}}
     *
     * @throws SaimsException
     */
    private function one(string $path): array
    {
        $body = $this->client->get($path);

        return is_array($body['data'] ?? null) ? $body['data'] : throw new SaimsException(SaimsFailure::Failed);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function customer(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'customer_name' => self::text($row['customer_name'] ?? null),
            'customer_type' => self::text($row['customer_type'] ?? null),
            'contact_person' => self::text($row['contact_person'] ?? null),
            'contact_number' => self::text($row['contact_number'] ?? null),
            'email' => self::text($row['email'] ?? null),
            'delivery_address' => self::text($row['delivery_address'] ?? null),
            'price_level' => self::text($row['price_level'] ?? null),
            'vat_type' => self::text($row['vat_type'] ?? null),
            'updated_at' => self::text($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function product(array $row): array
    {
        $prices = is_array($row['prices'] ?? null) ? $row['prices'] : [];
        $stock = is_array($row['stock'] ?? null) ? $row['stock'] : [];

        // by_location is {"Warehouse": 12, "POS": 3}, or an empty list when there is no stock.
        $locations = [];
        foreach (is_array($stock['by_location'] ?? null) ? $stock['by_location'] : [] as $name => $qty) {
            if (is_string($name)) {
                $locations[] = ['name' => $name, 'qty' => self::number($qty) ?? 0];
            }
        }

        $total = self::number($stock['total'] ?? null) ?? 0;
        $threshold = self::number($row['low_stock_threshold'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => self::text($row['code'] ?? null),
            'item_name' => self::text($row['item_name'] ?? null),
            'brand_name' => self::text($row['brand_name'] ?? null),
            'generic_name' => self::text($row['generic_name'] ?? null),
            'description' => self::text($row['description'] ?? null),
            'barcode' => self::text($row['barcode'] ?? null),
            'unit' => self::text($row['unit'] ?? null),
            'prices' => [
                'retail' => self::money($prices['retail'] ?? null),
                'wholesale' => self::money($prices['wholesale'] ?? null),
                'level_1' => self::money($prices['level_1'] ?? null),
                'level_2' => self::money($prices['level_2'] ?? null),
                'level_3' => self::money($prices['level_3'] ?? null),
            ],
            'low_stock_threshold' => $threshold,
            'fda_reg_no' => self::text($row['fda_reg_no'] ?? null),
            'stock' => ['by_location' => $locations, 'total' => $total],
            // The badge: total stock is at or below the threshold.
            'low_stock' => $threshold !== null && $total <= $threshold,
            'out_of_stock' => $total <= 0,
            'updated_at' => self::text($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function salesOrder(array $row): array
    {
        $items = array_map(function ($item) {
            $item = is_array($item) ? $item : [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'description' => self::text($item['description'] ?? null),
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'qty' => self::number($item['qty'] ?? null) ?? 0,
                'price' => self::money($item['price'] ?? null),
                'advance_qty' => self::number($item['advance_qty'] ?? null) ?? 0,
                'delivered_qty' => self::number($item['delivered_qty'] ?? null) ?? 0,
                'remaining_qty' => self::number($item['remaining_qty'] ?? null) ?? 0,
            ];
        }, array_values(is_array($row['items'] ?? null) ? $row['items'] : []));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'so_no' => self::text($row['so_no'] ?? null),
            'customer_po_no' => self::text($row['customer_po_no'] ?? null),
            'customer_po_date' => self::text($row['customer_po_date'] ?? null),
            'order_date' => self::text($row['order_date'] ?? null),
            'status' => self::text($row['status'] ?? null),
            'notes' => self::text($row['notes'] ?? null),
            'customer' => $this->party($row['customer'] ?? null),
            'items' => $items,
            // Sum of ordered qty x price, when every line has a price.
            'total' => round(array_sum(array_map(fn ($i) => $i['qty'] * ($i['price'] ?? 0), $items)), 2),
            'updated_at' => self::text($row['updated_at'] ?? null),
        ];
    }

    /**
     * SAIMS sends the invoice totals flat at the top level; the contract also
     * describes them under "totals". Both are accepted.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function invoice(array $row): array
    {
        $source = is_array($row['totals'] ?? null) ? $row['totals'] : $row;

        $totals = [];
        foreach ([
            'vat_sales', 'vat_exempt_sales', 'zero_rated_sales', 'vat_amount', 'total_sales',
            'less_vat', 'less_senior_pwd', 'less_withholding_tax', 'add_vat',
            'amount_net', 'amount_due', 'amount_paid',
        ] as $key) {
            $totals[$key] = self::money($source[$key] ?? ($row[$key] ?? null)) ?? 0.0;
        }

        $lines = array_map(function ($line) {
            $line = is_array($line) ? $line : [];

            return [
                'description' => self::text($line['description'] ?? null),
                'qty' => self::number($line['qty'] ?? null) ?? 0,
                'unit' => self::text($line['unit'] ?? null),
                'batch_no' => self::text($line['batch_no'] ?? null),
                'expiration_date' => self::text($line['expiration_date'] ?? null),
                'price' => self::money($line['price'] ?? null),
                'vat' => self::money($line['vat'] ?? null),
                'discount' => self::money($line['discount'] ?? null),
                'amount' => self::money($line['amount'] ?? null),
            ];
        }, array_values(is_array($row['lines'] ?? null) ? $row['lines'] : []));

        // Deliberately left out: is_personal_use.
        return [
            'id' => (int) ($row['id'] ?? 0),
            'sales_no' => self::text($row['sales_no'] ?? null),
            'customer_po_no' => self::text($row['customer_po_no'] ?? null),
            'date' => self::text($row['date'] ?? null),
            'customer' => $this->party($row['customer'] ?? null),
            'cancelled' => (bool) ($row['cancelled'] ?? false),
            'totals' => $totals,
            'lines' => $lines,
            'updated_at' => self::text($row['updated_at'] ?? null),
        ];
    }

    /** @return array{id: int, name: ?string}|null */
    private function party(mixed $customer): ?array
    {
        if (! is_array($customer)) {
            return null;
        }

        return ['id' => (int) ($customer['id'] ?? 0), 'name' => self::text($customer['name'] ?? null)];
    }

    /** @return array{state: string, message: string, response_ms: ?int, checked_at: string} */
    private function status(string $state, string $message, ?float $started): array
    {
        return [
            'state' => $state,
            'message' => $message,
            'response_ms' => $started === null ? null : (int) round((microtime(true) - $started) * 1000),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** "20.00" -> 20.0. Anything that is not numeric becomes null. */
    public static function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** A quantity: a whole number stays an int, otherwise a float. */
    public static function number(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    /** Plain text only (no arrays or objects). The UI escapes it again when rendering. */
    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
