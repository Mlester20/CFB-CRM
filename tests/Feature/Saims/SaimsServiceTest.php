<?php

namespace Tests\Feature\Saims;

use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsFailure;
use App\Services\Saims\SaimsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaimsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.saims' => [
            'enabled' => true,
            'url' => 'http://saims.test',
            'token' => SaimsClientTest::TOKEN,
            'timeout' => 5,
            'cache_seconds' => 60,
        ]]);
    }

    private function saims(): SaimsService
    {
        return app(SaimsService::class);
    }

    private function listOf(array $rows, array $meta = []): array
    {
        return ['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => count($rows), ...$meta]];
    }

    public function test_money_strings_become_numbers(): void
    {
        $this->assertSame(20.0, SaimsService::money('20.00'));
        $this->assertSame(1234.5, SaimsService::money('1234.50'));
        $this->assertSame(0.0, SaimsService::money('0.00'));
        $this->assertNull(SaimsService::money(null));
        $this->assertNull(SaimsService::money('abc'));
        $this->assertNull(SaimsService::money(['x']));

        $this->assertSame(10, SaimsService::number('10.00'));
        $this->assertSame(2.5, SaimsService::number('2.5'));
        $this->assertNull(SaimsService::number('n/a'));
    }

    public function test_a_product_is_cleaned_and_priced_in_numbers(): void
    {
        Http::fake(['*' => Http::response($this->listOf([[
            'id' => 7, 'code' => 'P-7', 'item_name' => 'Paracetamol', 'brand_name' => 'Biogesic', 'generic_name' => null,
            'description' => '<b>bold</b>', 'barcode' => '123', 'unit' => 'tablet',
            'prices' => ['retail' => '7.00', 'wholesale' => null, 'level_1' => '6.50', 'level_2' => null, 'level_3' => null],
            'low_stock_threshold' => 5, 'fda_reg_no' => 'FDA-1',
            'stock' => ['by_location' => ['Warehouse' => 10, 'POS' => '2.00'], 'total' => 12],
            'updated_at' => '2026-10-01 10:00:00',
        ]]))]);

        $product = $this->saims()->products()['items'][0];

        $this->assertSame(7.0, $product['prices']['retail']);
        $this->assertNull($product['prices']['wholesale']);
        $this->assertSame(6.5, $product['prices']['level_1']);
        $this->assertSame([['name' => 'Warehouse', 'qty' => 10], ['name' => 'POS', 'qty' => 2]], $product['stock']['by_location']);
        $this->assertSame(12, $product['stock']['total']);
        $this->assertFalse($product['low_stock']);
        $this->assertSame('<b>bold</b>', $product['description'], 'kept as plain text; the UI escapes it');
        $this->assertArrayNotHasKey('cost', $product);
    }

    public function test_the_low_stock_badge_follows_the_threshold(): void
    {
        $row = fn (int|float $total, ?int $threshold, array $by = []) => [
            'id' => 1, 'item_name' => 'X', 'prices' => [], 'low_stock_threshold' => $threshold,
            'stock' => ['by_location' => $by, 'total' => $total],
        ];

        Http::fake(['*' => Http::response($this->listOf([
            $row(10, 5),    // above the threshold
            $row(5, 5),     // at the threshold: low
            $row(3, 5),     // below: low
            $row(0, 0),     // nothing left, no threshold: out of stock, and 0 <= 0
            $row(2, null),  // no threshold: never low
        ]))]);

        $items = $this->saims()->products()['items'];

        $this->assertSame([false, true, true, true, false], array_column($items, 'low_stock'));
        $this->assertSame([false, false, false, true, false], array_column($items, 'out_of_stock'));
    }

    public function test_an_empty_stock_list_is_handled(): void
    {
        Http::fake(['*' => Http::response($this->listOf([
            ['id' => 1, 'item_name' => 'X', 'prices' => [], 'low_stock_threshold' => 0, 'stock' => ['by_location' => [], 'total' => 0]],
        ]))]);

        $this->assertSame([], $this->saims()->products()['items'][0]['stock']['by_location']);
    }

    public function test_a_sales_order_converts_prices_and_totals_the_lines(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'id' => 5, 'so_no' => 'SO-5', 'status' => 'partially_delivered', 'order_date' => '2026-10-27',
            'customer' => ['id' => 9, 'name' => 'ACME'],
            'items' => [
                ['id' => 1, 'description' => 'A', 'product_id' => null, 'qty' => 10, 'price' => '20.00', 'advance_qty' => 0, 'delivered_qty' => 8, 'remaining_qty' => 2],
                ['id' => 2, 'description' => 'B', 'product_id' => 4, 'qty' => '2', 'price' => '5.50', 'advance_qty' => 0, 'delivered_qty' => 0, 'remaining_qty' => 2],
            ],
        ]])]);

        $order = $this->saims()->salesOrderById(5);

        $this->assertSame(20.0, $order['items'][0]['price']);
        $this->assertNull($order['items'][0]['product_id']);
        $this->assertSame(4, $order['items'][1]['product_id']);
        $this->assertSame(8, $order['items'][0]['delivered_qty']);
        $this->assertSame(2, $order['items'][0]['remaining_qty']);
        $this->assertSame(211.0, $order['total']);
        $this->assertSame(['id' => 9, 'name' => 'ACME'], $order['customer']);
    }

    public function test_invoice_totals_are_read_whether_flat_or_nested_and_personal_use_is_dropped(): void
    {
        $flat = [
            'id' => 1, 'sales_no' => 'INV-1', 'date' => '2026-10-01', 'cancelled' => false, 'is_personal_use' => true,
            'customer' => ['id' => 2, 'name' => 'X'],
            'vat_sales' => '1000.00', 'vat_exempt_sales' => '0.00', 'zero_rated_sales' => '0.00', 'vat_amount' => '120.00',
            'total_sales' => '1120.00', 'less_vat' => '0.00', 'less_senior_pwd' => '0.00', 'less_withholding_tax' => '20.00',
            'add_vat' => '0.00', 'amount_net' => '1100.00', 'amount_due' => '1100.00', 'amount_paid' => '100.00',
            'lines' => [['description' => 'Item', 'qty' => 2, 'unit' => 'pc', 'batch_no' => 'B1', 'expiration_date' => '2029-12-31', 'price' => '500.00', 'vat' => '53.57', 'discount' => '0.00', 'amount' => '1000.00']],
        ];
        $nested = ['id' => 2, 'sales_no' => 'INV-2', 'customer' => null, 'totals' => ['amount_due' => '5.50', 'amount_paid' => '1.00', 'total_sales' => '6.50'], 'lines' => []];

        Http::fake(['*/invoices/1' => Http::response(['data' => $flat]), '*/invoices/2' => Http::response(['data' => $nested])]);

        $one = $this->saims()->invoiceById(1);
        $this->assertSame(1100.0, $one['totals']['amount_due']);
        $this->assertSame(100.0, $one['totals']['amount_paid']);
        $this->assertSame(500.0, $one['lines'][0]['price']);
        $this->assertSame(1000.0, $one['lines'][0]['amount']);
        $this->assertArrayNotHasKey('is_personal_use', $one);
        $this->assertStringNotContainsString('personal', json_encode($one));

        $two = $this->saims()->invoiceById(2);
        $this->assertSame(5.5, $two['totals']['amount_due']);
        $this->assertSame(0.0, $two['totals']['vat_sales'], 'a missing total is 0');
        $this->assertNull($two['customer']);
    }

    public function test_only_whitelisted_parameters_are_forwarded_and_per_page_is_clamped(): void
    {
        Http::fake(['*' => Http::response($this->listOf([]))]);

        $this->saims()->salesOrders([
            'customer_id' => '7', 'status' => 'open', 'from' => '2026-01-01', 'to' => '2026-12-31',
            'per_page' => 500, 'page' => 3,
            'search' => 'not allowed here', 'foo' => 'bar', 'updated_since' => '2020-01-01', 'sort' => 'x',
        ]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return $q === ['customer_id' => '7', 'from' => '2026-01-01', 'page' => '3', 'per_page' => '100', 'status' => 'open', 'to' => '2026-12-31'];
        });
    }

    public function test_bad_filter_values_are_dropped_not_forwarded(): void
    {
        Http::fake(['*' => Http::response($this->listOf([]))]);

        $this->saims()->salesOrders(['status' => 'hacked', 'from' => 'yesterday', 'customer_id' => '-5', 'per_page' => 0, 'page' => -2]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return $q === ['customer_id' => '1', 'page' => '1', 'per_page' => '1'];
        });
    }

    public function test_the_list_meta_is_normalised(): void
    {
        Http::fake(['*' => Http::response($this->listOf(array_fill(0, 3, ['id' => 1, 'customer_name' => 'A']), ['current_page' => 2, 'last_page' => 4, 'per_page' => 3, 'total' => 11]))]);

        $meta = $this->saims()->customers(['page' => 2, 'per_page' => 3])['meta'];

        $this->assertSame(['current_page' => 2, 'last_page' => 4, 'per_page' => 3, 'total' => 11, 'from' => 4, 'to' => 6], $meta);
    }

    public function test_a_search_is_forwarded_trimmed_and_limited(): void
    {
        Http::fake(['*' => Http::response($this->listOf([]))]);

        $this->saims()->customers(['search' => '  '.str_repeat('a', 300).'  ']);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return strlen($q['search']) === 100;
        });
    }

    public function test_detail_ids_are_integers_in_the_path(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 12, 'customer_name' => 'A']])]);

        $this->saims()->customerById(12);

        Http::assertSent(fn (Request $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/api/v1/customers/12'));
    }

    public function test_a_missing_record_is_a_not_found_failure(): void
    {
        Http::fake(['*' => Http::response(['message' => 'nope'], 404)]);

        try {
            $this->saims()->customerById(1);
            $this->fail('expected an exception');
        } catch (SaimsException $e) {
            $this->assertSame(SaimsFailure::NotFound, $e->failure);
        }
    }

    public function test_the_connection_check_reports_each_state_without_the_token(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->listOf([]))
            ->push([], 401)
            ->push([], 403)
            ->push([], 429)
            ->push([], 500)]);

        $ok = $this->saims()->checkConnection();
        $this->assertSame('connected', $ok['state']);
        $this->assertIsInt($ok['response_ms']);

        foreach ([401 => 'token_rejected', 403 => 'forbidden', 429 => 'rate_limited', 500 => 'failed'] as $status => $state) {
            $result = $this->saims()->checkConnection();

            $this->assertSame($state, $result['state']);
            $this->assertStringNotContainsString(SaimsClientTest::TOKEN, json_encode($result));
        }

        config(['services.saims.enabled' => false]);
        $this->assertSame('disabled', $this->saims()->checkConnection()['state']);
    }

    public function test_the_connection_check_always_uses_the_cheap_call_and_skips_the_cache(): void
    {
        Http::fake(['*' => Http::response($this->listOf([]))]);

        $this->saims()->checkConnection();
        $this->saims()->checkConnection();

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/customers') && $q['per_page'] === '1';
        });
    }
}
