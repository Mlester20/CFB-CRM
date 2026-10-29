<?php

namespace Tests\Feature\Saims;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class SaimsPagesTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private const TOKEN = SaimsClientTest::TOKEN;

    /** page => [list url, detail url, endpoint SAIMS is called on] */
    private const PAGES = [
        'customers' => ['/saims/customers', '/saims/customers/1', '/customers'],
        'products' => ['/saims/products', '/saims/products/1', '/products'],
        'orders' => ['/saims/orders', '/saims/orders/1', '/sales-orders'],
        'invoices' => ['/saims/invoices', '/saims/invoices/1', '/invoices'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        config(['services.saims' => [
            'enabled' => true,
            'url' => 'http://saims.test',
            'token' => self::TOKEN,
            'timeout' => 5,
            'cache_seconds' => 60,
        ]]);
    }

    private function list(array $rows): array
    {
        return ['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => count($rows)]];
    }

    /** SAIMS answering normally. Detail stubs come first: the first matching stub wins. */
    private function fakeSaims(): void
    {
        $customer = ['id' => 1, 'customer_name' => 'ACME TRADING', 'customer_type' => 'Private', 'email' => 'acme@example.com', 'price_level' => 'retail'];
        $product = ['id' => 1, 'code' => 'P-1', 'item_name' => 'PARACETAMOL', 'prices' => ['retail' => '7.00'], 'low_stock_threshold' => 5, 'stock' => ['by_location' => ['Warehouse' => 3], 'total' => 3]];
        $order = ['id' => 1, 'so_no' => 'SO-1', 'status' => 'open', 'order_date' => '2026-10-01', 'customer' => ['id' => 1, 'name' => 'ACME TRADING'], 'items' => [['id' => 1, 'description' => 'A', 'qty' => 10, 'price' => '20.00', 'delivered_qty' => 4, 'remaining_qty' => 6, 'advance_qty' => 0]]];
        $invoice = ['id' => 1, 'sales_no' => 'INV-1', 'date' => '2026-10-02', 'customer' => ['id' => 1, 'name' => 'ACME TRADING'], 'cancelled' => false, 'total_sales' => '1120.00', 'amount_due' => '1000.00', 'amount_paid' => '120.00', 'lines' => []];

        Http::fake([
            '*/api/v1/customers/*' => Http::response(['data' => $customer]),
            '*/api/v1/customers*' => Http::response($this->list([$customer])),
            '*/api/v1/products/*' => Http::response(['data' => $product]),
            '*/api/v1/products*' => Http::response($this->list([$product])),
            '*/api/v1/sales-orders/*' => Http::response(['data' => $order]),
            '*/api/v1/sales-orders*' => Http::response($this->list([$order])),
            '*/api/v1/invoices/*' => Http::response(['data' => $invoice]),
            '*/api/v1/invoices*' => Http::response($this->list([$invoice])),
        ]);
    }

    private function props($response): array
    {
        return $response->viewData('page')['props'];
    }

    private function sentTo(string $endpoint): bool
    {
        return Http::recorded(fn (Request $request) => str_contains(parse_url($request->url(), PHP_URL_PATH), '/api/v1'.$endpoint))->isNotEmpty();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        Http::fake();

        foreach (self::PAGES as [$list, $detail]) {
            $this->get($list)->assertRedirect('/login');
            $this->get($detail)->assertRedirect('/login');
        }
        $this->get('/admin/saims')->assertRedirect('/login');

        Http::assertNothingSent();
    }

    public static function rolesAndPages(): array
    {
        $all = ['customers' => 200, 'products' => 200, 'orders' => 200, 'invoices' => 200];
        $none = ['customers' => 403, 'products' => 403, 'orders' => 403, 'invoices' => 403];

        return [
            'Admin: all four' => ['Admin', $all],
            'Sales Manager: all four' => ['Sales Manager', $all],
            'Sales Rep: customers and products only' => ['Sales Rep', ['customers' => 200, 'products' => 200, 'orders' => 403, 'invoices' => 403]],
            'Viewer: none' => ['Viewer', $none],
        ];
    }

    #[DataProvider('rolesAndPages')]
    public function test_each_role_reaches_only_its_pages(string $role, array $expected): void
    {
        $this->fakeSaims();
        $user = $this->userWithRole($role);

        foreach (self::PAGES as $name => [$list, $detail]) {
            $this->actingAs($user)->get($list)->assertStatus($expected[$name]);
            $this->actingAs($user)->get($detail)->assertStatus($expected[$name]);
        }
    }

    public function test_a_user_without_any_role_gets_403_and_nothing_is_called(): void
    {
        Http::fake();
        $user = User::factory()->create();

        foreach (self::PAGES as [$list, $detail]) {
            $this->actingAs($user)->get($list)->assertForbidden();
            $this->actingAs($user)->get($detail)->assertForbidden();
        }

        Http::assertNothingSent();
    }

    public function test_a_rep_never_causes_a_request_to_orders_or_invoices(): void
    {
        $this->fakeSaims();
        $rep = $this->userWithRole('Sales Rep');

        // Everything a rep can open ...
        $this->actingAs($rep)->get('/saims/customers?search=a')->assertOk();
        $this->actingAs($rep)->get('/saims/customers/1')->assertOk();
        $this->actingAs($rep)->get('/saims/products')->assertOk();
        $this->actingAs($rep)->get('/saims/products/1')->assertOk();
        $this->actingAs($rep)->get('/dashboard')->assertOk();
        $this->actingAs($rep)->get('/leads')->assertOk();

        // ... and everything they try but may not.
        $this->actingAs($rep)->get('/saims/orders')->assertForbidden();
        $this->actingAs($rep)->get('/saims/orders/1')->assertForbidden();
        $this->actingAs($rep)->get('/saims/invoices?customer_id=1')->assertForbidden();
        $this->actingAs($rep)->get('/saims/invoices/1')->assertForbidden();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/sales-orders') || str_contains($request->url(), '/invoices'));
        $this->assertTrue($this->sentTo('/customers'));
        $this->assertTrue($this->sentTo('/products'));
    }

    public function test_a_viewer_never_causes_any_request(): void
    {
        Http::fake();
        $viewer = $this->userWithRole('Viewer');

        foreach (self::PAGES as [$list, $detail]) {
            $this->actingAs($viewer)->get($list)->assertForbidden();
            $this->actingAs($viewer)->get($detail)->assertForbidden();
        }
        $this->actingAs($viewer)->get('/dashboard')->assertOk();
        $this->actingAs($viewer)->get('/leads')->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_customer_filter_only_calls_customers_for_users_who_may_read_them(): void
    {
        $this->fakeSaims();

        // A user who may read orders but not customers.
        $ordersOnly = User::factory()->create();
        $ordersOnly->givePermissionTo('saims.orders');

        $props = $this->props($this->actingAs($ordersOnly)->get('/saims/orders'));
        $this->assertSame([], $props['customers']);
        $this->assertFalse($this->sentTo('/customers'));
        $this->assertTrue($this->sentTo('/sales-orders'));

        // A manager gets the customer filter, from one cached customers call.
        $manager = $this->userWithRole('Sales Manager');
        $props = $this->props($this->actingAs($manager)->get('/saims/orders'));
        $this->assertSame([['id' => 1, 'name' => 'ACME TRADING']], $props['customers']);
        $this->assertTrue($this->sentTo('/customers'));
    }

    public function test_the_list_pages_give_clean_props(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $customers = $this->props($this->actingAs($admin)->get('/saims/customers'));
        $this->assertSame('ACME TRADING', $customers['records']['items'][0]['customer_name']);
        $this->assertSame(1, $customers['records']['meta']['total']);
        $this->assertNull($customers['error']);
        $this->assertNull($customers['drawer']);

        $products = $this->props($this->actingAs($admin)->get('/saims/products'));
        $this->assertSame(7.0, $products['records']['items'][0]['prices']['retail']);
        $this->assertTrue($products['records']['items'][0]['low_stock'], '3 in stock, threshold 5');

        $orders = $this->props($this->actingAs($admin)->get('/saims/orders'));
        $this->assertSame(['open', 'partially_delivered', 'completed', 'cancelled'], $orders['statuses']);
        $this->assertSame(200.0, $orders['records']['items'][0]['total']);

        $invoices = $this->props($this->actingAs($admin)->get('/saims/invoices'));
        $this->assertSame(1000.0, $invoices['records']['items'][0]['totals']['amount_due']);
    }

    public function test_the_detail_routes_open_the_drawer(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        foreach (self::PAGES as [, $detail]) {
            $drawer = $this->props($this->actingAs($admin)->get($detail))['drawer'];

            $this->assertSame(1, $drawer['id']);
            $this->assertNull($drawer['error']);
            $this->assertIsArray($drawer['record']);
        }
    }

    // ------------------------------------------------------------- token safety

    public function test_the_token_never_appears_in_any_page_props_or_html(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');
        $rep = $this->userWithRole('Sales Rep');

        $urls = ['/dashboard', '/admin/saims', '/saims/customers/1', '/saims/orders/1', '/saims/invoices', '/saims/products?search=x'];
        foreach ($urls as $url) {
            $body = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString(self::TOKEN, $body, $url);
        }

        // error pages: forbidden and not found
        $this->assertStringNotContainsString(self::TOKEN, $this->actingAs($rep)->get('/saims/orders')->assertForbidden()->getContent());
        $this->assertStringNotContainsString(self::TOKEN, $this->actingAs($admin)->get('/saims/nothing')->assertNotFound()->getContent());

        // and the Inertia JSON that the frontend navigates with
        $json = $this->actingAs($admin)->get('/saims/customers', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $json);
    }

    public function test_the_token_never_appears_when_saims_fails(): void
    {
        $admin = $this->userWithRole('Admin');

        foreach ([401, 403, 404, 429, 500] as $status) {
            Http::fake(['*' => Http::response(['error' => 'leaked '.self::TOKEN], $status)]);
            cache()->flush();

            foreach (['/saims/customers', '/saims/products', '/saims/orders', '/saims/invoices', '/admin/saims'] as $url) {
                $response = $this->actingAs($admin)->get($url);

                $this->assertStringNotContainsString(self::TOKEN, $response->getContent(), "{$status} {$url}");
                $this->assertStringNotContainsString('leaked', $response->getContent(), "{$status} {$url}: SAIMS's own text must not be shown");
            }
        }

        Http::fake(['*' => function () {
            throw new ConnectionException('failed to connect, token '.self::TOKEN);
        }]);
        cache()->flush();

        $this->assertStringNotContainsString(self::TOKEN, $this->actingAs($admin)->get('/saims/customers')->getContent());
    }

    // ----------------------------------------------------------- error handling

    public static function failures(): array
    {
        return [
            '401' => [401, 'token_rejected'],
            '403' => [403, 'forbidden'],
            '429' => [429, 'rate_limited'],
            '500' => [500, 'failed'],
            '404 on a list' => [404, 'not_found'],
        ];
    }

    #[DataProvider('failures')]
    public function test_a_saims_error_shows_a_friendly_message_and_the_page_still_works(int $status, string $code): void
    {
        Http::fake([
            '*/api/v1/customers*' => Http::response([], $status),
            '*' => Http::response($this->list([])),
        ]);
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAs($admin)->get('/saims/customers')->assertOk();
        $props = $this->props($response);

        $this->assertSame($code, $props['error']['code']);
        $this->assertNotEmpty($props['error']['message']);
        $this->assertNull($props['records']);

        // The rest of the CRM is unaffected.
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/leads')->assertOk();
        $this->actingAs($admin)->get('/saims/products')->assertOk();
    }

    public function test_saims_being_down_shows_an_unreachable_message_and_does_not_break_the_crm(): void
    {
        Http::fake(['*' => function () {
            throw new ConnectionException('connection refused');
        }]);
        $admin = $this->userWithRole('Admin');

        foreach (['/saims/customers', '/saims/products', '/saims/orders', '/saims/invoices'] as $url) {
            $props = $this->props($this->actingAs($admin)->get($url)->assertOk());

            $this->assertSame('unreachable', $props['error']['code']);
            $this->assertNull($props['records']);
        }

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_a_missing_record_is_a_404_page_and_other_detail_errors_keep_the_list(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake([
            '*/api/v1/customers/*' => Http::response([], 404),
            '*/api/v1/products/*' => Http::response([], 500),
            '*' => Http::response($this->list([])),
        ]);

        $this->actingAs($admin)->get('/saims/customers/99')->assertNotFound();

        $props = $this->props($this->actingAs($admin)->get('/saims/products/5')->assertOk());
        $this->assertSame('failed', $props['drawer']['error']['code']);
        $this->assertNull($props['drawer']['record']);
        $this->assertNull($props['error'], 'the list itself loaded fine');
    }

    public function test_an_unconfigured_or_disabled_saims_shows_a_not_connected_state_without_calling(): void
    {
        Http::fake();
        $admin = $this->userWithRole('Admin');

        config(['services.saims.enabled' => false]);
        $this->assertSame('disabled', $this->props($this->actingAs($admin)->get('/saims/customers')->assertOk())['error']['code']);

        config(['services.saims.enabled' => true, 'services.saims.token' => '', 'services.saims.url' => '']);
        $this->assertSame('not_configured', $this->props($this->actingAs($admin)->get('/saims/products')->assertOk())['error']['code']);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ caching

    public function test_a_repeated_identical_page_request_makes_one_http_call(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/saims/customers?search=acme')->assertOk();
        $this->actingAs($admin)->get('/saims/customers?search=acme')->assertOk();
        $this->actingAs($this->userWithRole('Sales Rep'))->get('/saims/customers?search=acme')->assertOk();

        Http::assertSentCount(1);

        $this->actingAs($admin)->get('/saims/customers?search=other')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_opening_a_detail_does_not_call_the_list_once_per_row(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/saims/orders')->assertOk();
        $before = Http::recorded()->count();

        $this->actingAs($admin)->get('/saims/orders/1')->assertOk();

        // the list and the customers are cached; only the one detail is new
        $this->assertSame($before + 1, Http::recorded()->count());
    }

    // ------------------------------------------------- parameter whitelisting

    public function test_unknown_query_parameters_are_not_forwarded_and_per_page_is_clamped(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/saims/orders?foo=bar&token=abc&url=http://evil.test&per_page=500&status=open&customer_id=1')->assertOk();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/sales-orders')) {
                return false;
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return $q === ['customer_id' => '1', 'page' => '1', 'per_page' => '100', 'status' => 'open'];
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil') || str_contains($request->url(), 'foo='));
    }

    public static function badQueries(): array
    {
        return [
            'page zero' => ['/saims/customers?page=0'],
            'page text' => ['/saims/products?page=abc'],
            'per_page zero' => ['/saims/customers?per_page=0'],
            'search too long' => ['/saims/customers?search='.'a'.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            'customer id text' => ['/saims/orders?customer_id=abc'],
            'customer id negative' => ['/saims/invoices?customer_id=-3'],
            'unknown status' => ['/saims/orders?status=hacked'],
            'bad date' => ['/saims/orders?from=2026-13-45'],
            'date in other format' => ['/saims/invoices?from=10/01/2026'],
            'to before from' => ['/saims/invoices?from=2026-10-10&to=2026-10-01'],
        ];
    }

    #[DataProvider('badQueries')]
    public function test_bad_parameters_are_rejected_without_calling_saims(string $url): void
    {
        Http::fake();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->from('/saims/customers')->get($url)->assertSessionHasErrors();

        Http::assertNothingSent();
    }

    public function test_a_bad_id_is_a_404_and_calls_nothing(): void
    {
        Http::fake();
        $admin = $this->userWithRole('Admin');

        foreach (['/saims/customers/abc', '/saims/products/1.5', '/saims/orders/-1', '/saims/invoices/1;drop'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }

        Http::assertNothingSent();
    }

    public function test_valid_filters_are_forwarded(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/saims/invoices?customer_id=9&from=2026-01-01&to=2026-12-31&page=2')->assertOk();

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return str_contains($request->url(), '/invoices') && $q === ['customer_id' => '9', 'from' => '2026-01-01', 'page' => '2', 'per_page' => '15', 'to' => '2026-12-31'];
        });
    }

    public function test_the_pages_only_read(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        foreach (self::PAGES as [$list, $detail]) {
            $this->actingAs($admin)->get($list);
            $this->actingAs($admin)->get($detail);
        }
        $this->actingAs($admin)->get('/admin/saims');

        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');

        // and no write routes exist for these pages
        foreach (self::PAGES as [$list, $detail]) {
            $this->actingAs($admin)->post($list)->assertStatus(405);
            $this->actingAs($admin)->put($detail)->assertStatus(405);
            $this->actingAs($admin)->delete($detail)->assertStatus(405);
        }
    }

    // ------------------------------------------------------- connection status

    public function test_only_users_who_can_manage_roles_see_the_connection_status(): void
    {
        $this->fakeSaims();

        $this->actingAs($this->userWithRole('Admin'))->get('/admin/saims')->assertOk();

        foreach (['Sales Manager', 'Sales Rep', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/admin/saims')->assertForbidden();
        }
    }

    public function test_the_connection_page_reports_the_state_and_never_the_token(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $props = $this->props($this->actingAs($admin)->get('/admin/saims')->assertOk());

        $this->assertSame('connected', $props['status']['state']);
        $this->assertIsInt($props['status']['response_ms']);
        $this->assertSame('saims.test', $props['settings']['server']);
        $this->assertTrue($props['settings']['configured']);
        $this->assertArrayNotHasKey('token', $props['settings']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($props));
    }

    public function test_the_connection_page_shows_unreachable_and_token_rejected(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake(['*' => Http::response([], 401)]);
        $this->assertSame('token_rejected', $this->props($this->actingAs($admin)->get('/admin/saims'))['status']['state']);

        cache()->flush();
        Http::fake(['*' => function () {
            throw new ConnectionException('refused');
        }]);
        $props = $this->props($this->actingAs($admin)->get('/admin/saims'));
        $this->assertSame('unreachable', $props['status']['state']);
        $this->assertNull($props['status']['response_ms']);
    }

    public function test_reloading_the_connection_page_does_not_burn_the_rate_limit(): void
    {
        $this->fakeSaims();
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/admin/saims')->assertOk();
        $this->actingAs($admin)->get('/admin/saims')->assertOk();
        $this->actingAs($admin)->get('/admin/saims?refresh=1')->assertOk(); // first recheck is allowed
        $this->actingAs($admin)->get('/admin/saims?refresh=1')->assertOk(); // a second one within 5 s is not

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'per_page=1') && str_contains($request->url(), '/customers'));
    }
}
