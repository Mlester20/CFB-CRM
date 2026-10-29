<?php

namespace Tests\Feature\Saims;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Linking a lead to a SAIMS customer, the picker, and the lazy SAIMS panel.
 */
class LeadSaimsTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private const TOKEN = SaimsClientTest::TOKEN;

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

    // ------------------------------------------------------------------- helpers

    private function customer(int $id = 42, string $name = 'ACME TRADING'): array
    {
        return ['id' => $id, 'customer_name' => $name, 'customer_type' => 'Private', 'contact_person' => 'Juan', 'contact_number' => '0917', 'delivery_address' => 'Baguio', 'price_level' => 'retail', 'email' => 'a@example.com'];
    }

    private function list(array $rows, ?int $total = null): array
    {
        return ['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => $total ?? count($rows)]];
    }

    private function order(int $id, array $items = []): array
    {
        return ['id' => $id, 'so_no' => "SO-{$id}", 'status' => 'open', 'order_date' => '2026-10-0'.$id, 'customer' => ['id' => 42, 'name' => 'ACME'], 'items' => $items ?: [['id' => 1, 'description' => 'A', 'qty' => 10, 'price' => '2.00', 'delivered_qty' => 4, 'remaining_qty' => 6, 'advance_qty' => 0]]];
    }

    private function invoice(int $id, string $due, string $paid, bool $cancelled = false): array
    {
        return ['id' => $id, 'sales_no' => "INV-{$id}", 'date' => '2026-10-1'.$id, 'cancelled' => $cancelled, 'customer' => ['id' => 42, 'name' => 'ACME'], 'total_sales' => '120.00', 'amount_due' => $due, 'amount_paid' => $paid, 'lines' => []];
    }

    /** SAIMS answering normally. Detail stubs first: the first matching stub wins. */
    private function fakeSaims(array $invoices = null, array $orders = null, ?int $invoiceTotal = null): void
    {
        Http::fake([
            '*/api/v1/customers/42' => Http::response(['data' => $this->customer()]),
            '*/api/v1/customers/43' => Http::response(['data' => $this->customer(43, 'BETA STORE')]),
            '*/api/v1/customers/999' => Http::response([], 404),
            '*/api/v1/customers*' => Http::response($this->list([$this->customer(), $this->customer(43, 'BETA STORE')])),
            '*/api/v1/sales-orders*' => Http::response($this->list($orders ?? [$this->order(1), $this->order(2)])),
            '*/api/v1/invoices*' => Http::response($this->list($invoices ?? [$this->invoice(1, '100.00', '20.00'), $this->invoice(2, '50.00', '50.00'), $this->invoice(3, '999.00', '0.00', true)], $invoiceTotal)),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Maria Santos', 'company' => 'Santos Trading', 'email' => 'maria@example.ph', 'phone' => '0917', 'stage' => 'new', 'priority' => 'medium', 'source' => 'website', 'value' => '1000', 'notes' => null, ...$overrides];
    }

    private function linked(User $owner, int $id = 42, string $name = 'ACME TRADING'): Lead
    {
        return Lead::factory()->ownedBy($owner)->create(['saims_customer_id' => $id, 'saims_customer_name' => $name]);
    }

    private function sentTo(string $endpoint): bool
    {
        return Http::recorded(fn (Request $request) => str_contains(parse_url($request->url(), PHP_URL_PATH), '/api/v1'.$endpoint))->isNotEmpty();
    }

    // ================================================================ PART 1: link

    public function test_a_manager_can_link_a_lead_and_the_name_comes_from_saims(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload([
            'saims_customer_id' => 42,
            'saims_customer_name' => 'A NAME THE BROWSER MADE UP', // never read
        ]))->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame(42, $lead->saims_customer_id);
        $this->assertSame('ACME TRADING', $lead->saims_customer_name);
        $this->assertTrue($this->sentTo('/customers/42'), 'the customer is checked in SAIMS');
    }

    public function test_a_lead_can_be_linked_while_it_is_created(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');

        $this->actingAs($manager)->post('/leads', $this->payload(['saims_customer_id' => 43]))->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertSame(43, $lead->saims_customer_id);
        $this->assertSame('BETA STORE', $lead->saims_customer_name);
    }

    public function test_unlinking_clears_both_columns_without_calling_saims(): void
    {
        Http::fake();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => null]))->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertNull($lead->saims_customer_id);
        $this->assertNull($lead->saims_customer_name);
        Http::assertNothingSent();
    }

    public function test_an_unchanged_link_makes_no_saims_call_and_a_changed_one_is_checked(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['name' => 'Renamed', 'saims_customer_id' => 42]))->assertSessionHasNoErrors();
        Http::assertNothingSent();

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => 43]))->assertSessionHasNoErrors();
        $this->assertTrue($this->sentTo('/customers/43'));
        $this->assertSame('BETA STORE', $lead->fresh()->saims_customer_name);
    }

    public function test_a_rep_can_link_their_own_lead_but_never_someone_elses(): void
    {
        $this->fakeSaims();
        $rep = $this->userWithRole('Sales Rep');
        $other = $this->userWithRole('Sales Rep');
        $mine = Lead::factory()->ownedBy($rep)->create();
        $theirs = Lead::factory()->ownedBy($other)->create();

        $this->actingAs($rep)->put("/leads/{$mine->id}", $this->payload(['saims_customer_id' => 42]))->assertSessionHasNoErrors();
        $this->assertSame(42, $mine->fresh()->saims_customer_id);

        $this->actingAs($rep)->put("/leads/{$theirs->id}", $this->payload(['saims_customer_id' => 42]))->assertNotFound();
        $this->assertNull($theirs->fresh()->saims_customer_id);
    }

    public function test_a_viewer_cannot_link_because_they_cannot_update(): void
    {
        $this->fakeSaims();
        $viewer = $this->userWithRole('Viewer');
        $viewer->givePermissionTo('saims.customers'); // even with the SAIMS permission
        $lead = Lead::factory()->create();

        $this->actingAs($viewer)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => 42]))->assertForbidden();

        $this->assertNull($lead->fresh()->saims_customer_id);
    }

    public function test_without_saims_customers_the_field_is_ignored_and_the_link_is_untouched(): void
    {
        $this->fakeSaims();
        Role::findByName('Sales Rep')->revokePermissionTo('saims.customers');
        $rep = $this->userWithRole('Sales Rep');

        $plain = Lead::factory()->ownedBy($rep)->create();
        $this->actingAs($rep)->put("/leads/{$plain->id}", $this->payload(['saims_customer_id' => 42]))->assertSessionHasNoErrors();
        $this->assertNull($plain->fresh()->saims_customer_id, 'cannot link');

        // not even a nonsense value is validated: the field simply does not exist for this user
        $this->actingAs($rep)->put("/leads/{$plain->id}", $this->payload(['saims_customer_id' => 'garbage']))->assertSessionHasNoErrors();

        // editing a linked lead keeps its link, and trying to unlink does nothing
        $linked = $this->linked($rep);
        $this->actingAs($rep)->put("/leads/{$linked->id}", $this->payload(['name' => 'Edited']))->assertSessionHasNoErrors();
        $this->actingAs($rep)->put("/leads/{$linked->id}", $this->payload(['saims_customer_id' => null]))->assertSessionHasNoErrors();
        $this->assertSame(42, $linked->fresh()->saims_customer_id);
        $this->assertSame('ACME TRADING', $linked->fresh()->saims_customer_name);

        // creating a lead does not link either
        $this->actingAs($rep)->post('/leads', $this->payload(['saims_customer_id' => 42]))->assertSessionHasNoErrors();
        $this->assertNull(Lead::where('name', 'Maria Santos')->firstOrFail()->saims_customer_id);

        Http::assertNothingSent();
    }

    public function test_a_customer_that_does_not_exist_in_saims_cannot_be_linked(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create(['name' => 'Original']);

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['name' => 'Changed', 'saims_customer_id' => 999]))
            ->assertSessionHasErrors(['saims_customer_id' => 'That customer was not found in SAIMS.']);

        $this->assertSame('Original', $lead->fresh()->name, 'nothing was saved');
        $this->assertNull($lead->fresh()->saims_customer_id);
    }

    public function test_when_saims_is_down_the_link_is_refused_with_a_friendly_message(): void
    {
        Http::fake(['*' => function () {
            throw new ConnectionException('connection refused, token '.self::TOKEN);
        }]);
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $response = $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => 42]))
            ->assertSessionHasErrors('saims_customer_id');

        $this->assertStringContainsString('SAIMS could not be checked', session('errors')->first('saims_customer_id'));
        $this->assertStringNotContainsString(self::TOKEN, json_encode(session('errors')->all()));
        $this->assertNull($lead->fresh()->saims_customer_id);

        // saving the lead without touching the link still works while SAIMS is down
        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['name' => 'Still saves']))->assertSessionHasNoErrors();
        $this->assertSame('Still saves', $lead->fresh()->name);
    }

    public function test_the_customer_id_must_be_a_positive_integer(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        foreach (['abc', '-4', '0', '1.5'] as $bad) {
            $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => $bad]))->assertSessionHasErrors('saims_customer_id');
        }

        Http::assertNothingSent();
    }

    public function test_the_link_is_shown_according_to_the_users_saims_permissions(): void
    {
        Http::fake();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        // saims.customers: sees which customer
        $row = $this->actingAs($manager)->get("/leads/{$lead->id}")->viewData('page')['props']['drawer']['lead'];
        $this->assertTrue($row['saims_linked']);
        $this->assertSame(['id' => 42, 'name' => 'ACME TRADING'], $row['saims_customer']);

        // only saims.orders: knows it is linked, but not the customer
        $ordersOnly = $this->userWithRole('Viewer');
        $ordersOnly->givePermissionTo('saims.orders');
        $row = $this->actingAs($ordersOnly)->get("/leads/{$lead->id}")->viewData('page')['props']['drawer']['lead'];
        $this->assertTrue($row['saims_linked']);
        $this->assertArrayNotHasKey('saims_customer', $row);

        // no saims permission: nothing at all
        $viewer = $this->userWithRole('Viewer');
        $row = $this->actingAs($viewer)->get("/leads/{$lead->id}")->viewData('page')['props']['drawer']['lead'];
        $this->assertArrayNotHasKey('saims_linked', $row);
        $this->assertArrayNotHasKey('saims_customer', $row);
        $this->assertStringNotContainsString('ACME', json_encode($this->actingAs($viewer)->get('/leads')->viewData('page')['props']));

        Http::assertNothingSent();
    }

    public function test_the_lead_list_and_drawer_make_no_saims_call(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);
        Lead::factory()->count(5)->ownedBy($manager)->create(['saims_customer_id' => 42, 'saims_customer_name' => 'ACME TRADING']);

        $rows = $this->actingAs($manager)->get('/leads')->viewData('page')['props']['leads']['data'];
        $this->assertCount(6, $rows);
        $this->assertTrue($rows[0]['saims_linked']);

        $this->actingAs($manager)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($manager)->get("/leads/{$lead->id}/edit")->assertOk();
        $this->actingAs($manager)->get('/leads/create')->assertOk();

        Http::assertNothingSent();
    }

    // ============================================================== the picker

    public function test_the_picker_searches_saims_with_a_capped_result(): void
    {
        $many = array_map(fn ($i) => $this->customer($i, "CUSTOMER {$i}"), range(1, 25));
        Http::fake(['*' => Http::response($this->list($many))]);
        $manager = $this->userWithRole('Sales Manager');

        $json = $this->actingAs($manager)->getJson('/leads/saims-customers?search=cust&evil=1&per_page=500')->assertOk()->json();

        $this->assertCount(10, $json['items'], 'capped at 10 even if SAIMS answers with more');
        $this->assertSame(['id', 'name', 'type'], array_keys($json['items'][0]));
        $this->assertNull($json['error']);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return $q === ['page' => '1', 'per_page' => '10', 'search' => 'cust'];
        });
    }

    public function test_the_picker_is_only_for_users_with_saims_customers(): void
    {
        $this->fakeSaims();

        $this->actingAs($this->userWithRole('Sales Manager'))->getJson('/leads/saims-customers?search=a')->assertOk();
        $this->actingAs($this->userWithRole('Sales Rep'))->getJson('/leads/saims-customers?search=a')->assertOk();

        Http::fake();
        $noSaims = $this->userWithRole('Viewer');
        $this->actingAs($noSaims)->getJson('/leads/saims-customers?search=a')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/leads/saims-customers')->assertForbidden();
    }

    public function test_guests_cannot_use_the_picker_or_the_panel(): void
    {
        Http::fake();
        $lead = $this->linked($this->userWithRole('Sales Manager'));

        $this->get('/leads/saims-customers')->assertRedirect('/login');
        $this->getJson('/leads/saims-customers')->assertUnauthorized();
        $this->getJson("/leads/{$lead->id}/saims")->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_picker_validates_its_input_and_survives_saims_errors(): void
    {
        $manager = $this->userWithRole('Sales Manager');

        Http::fake();
        $this->actingAs($manager)->getJson('/leads/saims-customers?search='.str_repeat('a', 101))->assertStatus(422);
        Http::assertNothingSent();

        Http::fake(['*' => Http::response([], 500)]);
        $json = $this->actingAs($manager)->getJson('/leads/saims-customers?search=x')->assertOk()->json();
        $this->assertSame([], $json['items']);
        $this->assertSame('failed', $json['error']['code']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($json));
    }

    // ============================================================ PART 2: panel

    private function panel(User $user, Lead $lead)
    {
        return $this->actingAs($user)->getJson("/leads/{$lead->id}/saims");
    }

    public function test_the_panel_shows_each_block_only_with_its_permission(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $admin = $this->userWithRole('Admin');
        $lead = $this->linked($manager);

        foreach ([$manager, $admin] as $user) {
            $json = $this->panel($user, $lead)->assertOk()->json();
            $this->assertTrue($json['linked']);
            $this->assertSame(['customer', 'orders', 'invoices'], array_keys($json['blocks']));
        }

        // the customers-only user (a Sales Rep) gets the customer card and nothing else
        $rep = $this->userWithRole('Sales Rep');
        $repLead = $this->linked($rep);
        $json = $this->panel($rep, $repLead)->assertOk()->json();
        $this->assertSame(['customer'], array_keys($json['blocks']));
        $this->assertSame('ACME TRADING', $json['blocks']['customer']['data']['customer_name']);

        // orders only
        $ordersOnly = $this->userWithRole('Viewer');
        $ordersOnly->givePermissionTo('saims.orders');
        $this->assertSame(['orders'], array_keys($this->panel($ordersOnly, $lead)->assertOk()->json('blocks')));

        // invoices only
        $invoicesOnly = $this->userWithRole('Viewer');
        $invoicesOnly->givePermissionTo('saims.invoices');
        $this->assertSame(['invoices'], array_keys($this->panel($invoicesOnly, $lead)->assertOk()->json('blocks')));
    }

    public function test_a_rep_causes_no_orders_or_invoices_calls(): void
    {
        $this->fakeSaims();
        $rep = $this->userWithRole('Sales Rep');
        $lead = $this->linked($rep);

        $this->panel($rep, $lead)->assertOk();
        $this->actingAs($rep)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($rep)->get('/leads')->assertOk();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/sales-orders') || str_contains($request->url(), '/invoices'));
        $this->assertTrue($this->sentTo('/customers/42'));
    }

    public function test_a_user_without_any_saims_permission_gets_403_and_causes_no_call(): void
    {
        Http::fake();
        $viewer = $this->userWithRole('Viewer');
        $lead = $this->linked($this->userWithRole('Sales Manager'));

        $this->panel($viewer, $lead)->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_an_unlinked_lead_has_no_panel_and_causes_no_call(): void
    {
        Http::fake();
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $json = $this->panel($manager, $lead)->assertOk()->json();

        $this->assertFalse($json['linked']);
        $this->assertSame([], $json['blocks']);
        Http::assertNothingSent();
    }

    public function test_the_panel_respects_the_lead_scope(): void
    {
        Http::fake();
        $rep = $this->userWithRole('Sales Rep');
        $theirs = $this->linked($this->userWithRole('Sales Rep'));

        $this->panel($rep, $theirs)->assertNotFound();
        $this->actingAs($rep)->getJson('/leads/999999/saims')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_orders_block_summarises_delivered_against_ordered(): void
    {
        $this->fakeSaims(orders: [
            $this->order(1, [['id' => 1, 'description' => 'A', 'qty' => 10, 'price' => '2.00', 'delivered_qty' => 4, 'remaining_qty' => 6, 'advance_qty' => 0], ['id' => 2, 'description' => 'B', 'qty' => '5', 'price' => '1.00', 'delivered_qty' => 5, 'remaining_qty' => 0, 'advance_qty' => 0]]),
        ]);
        $manager = $this->userWithRole('Sales Manager');

        $orders = $this->panel($manager, $this->linked($manager))->assertOk()->json('blocks.orders.data');

        $this->assertSame(1, $orders['total']);
        $this->assertSame(['id' => 1, 'so_no' => 'SO-1', 'order_date' => '2026-10-01', 'status' => 'open', 'ordered' => 15, 'delivered' => 9], $orders['items'][0]);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/sales-orders')) {
                return false;
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return $q === ['customer_id' => '42', 'page' => '1', 'per_page' => '5'];
        });
    }

    public function test_the_invoices_block_totals_outstanding_and_paid_without_cancelled_ones(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');

        $invoices = $this->panel($manager, $this->linked($manager))->assertOk()->json('blocks.invoices.data');

        $this->assertEquals(150.0, $invoices['outstanding'], '100 + 50; the cancelled 999 is left out');
        $this->assertEquals(70.0, $invoices['paid'], '20 + 50');
        $this->assertFalse($invoices['partial']);
        $this->assertSame(3, $invoices['total']);
        $this->assertSame(['id', 'sales_no', 'date', 'cancelled', 'total_sales', 'amount_due', 'amount_paid'], array_keys($invoices['items'][0]));
        $this->assertTrue($invoices['items'][2]['cancelled']);
        $this->assertEquals(120.0, $invoices['items'][0]['total_sales']);
    }

    public function test_only_five_invoices_are_listed_and_a_large_history_is_flagged_partial(): void
    {
        $many = array_map(fn ($i) => $this->invoice($i, '10.00', '0.00'), range(1, 8));
        $this->fakeSaims(invoices: $many, invoiceTotal: 250);
        $manager = $this->userWithRole('Sales Manager');

        $invoices = $this->panel($manager, $this->linked($manager))->assertOk()->json('blocks.invoices.data');

        $this->assertCount(5, $invoices['items']);
        $this->assertTrue($invoices['partial'], '250 invoices exist but only the latest were read');
        $this->assertEquals(80.0, $invoices['outstanding']);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return str_contains($request->url(), '/invoices') && $q['per_page'] === '100' && $q['customer_id'] === '42';
        });
    }

    public function test_a_customer_that_no_longer_exists_only_breaks_its_own_block(): void
    {
        Http::fake([
            '*/api/v1/customers/42' => Http::response([], 404),
            '*/api/v1/sales-orders*' => Http::response($this->list([$this->order(1)])),
            '*/api/v1/invoices*' => Http::response($this->list([$this->invoice(1, '10.00', '0.00')])),
        ]);
        $manager = $this->userWithRole('Sales Manager');

        $blocks = $this->panel($manager, $this->linked($manager))->assertOk()->json('blocks');

        $this->assertSame('not_found', $blocks['customer']['error']['code']);
        $this->assertNull($blocks['customer']['data']);
        $this->assertNotNull($blocks['orders']['data']);
        $this->assertNotNull($blocks['invoices']['data']);
    }

    public function test_when_saims_is_down_every_block_shows_a_friendly_error_and_the_drawer_still_opens(): void
    {
        Http::fake(['*' => function () {
            throw new ConnectionException('refused '.self::TOKEN);
        }]);
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        $response = $this->panel($manager, $lead)->assertOk();
        foreach ($response->json('blocks') as $block) {
            $this->assertSame('unreachable', $block['error']['code']);
            $this->assertNotEmpty($block['error']['message']);
        }
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());

        // the drawer itself (the page) is unaffected
        $this->actingAs($manager)->get("/leads/{$lead->id}")->assertOk();
    }

    public function test_each_saims_error_status_becomes_a_friendly_block_error(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        // One fake that answers with whatever status is current (a second Http::fake would not replace it).
        $status = 500;
        Http::fake(function () use (&$status) {
            return Http::response(['detail' => 'leaked '.self::TOKEN], $status);
        });

        foreach ([401 => 'token_rejected', 403 => 'forbidden', 429 => 'rate_limited', 500 => 'failed'] as $status => $code) {
            cache()->flush();

            $response = $this->panel($manager, $lead)->assertOk();

            $this->assertSame($code, $response->json('blocks.customer.error.code'));
            $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
            $this->assertStringNotContainsString('leaked', $response->getContent());
        }
    }

    public function test_a_repeated_panel_request_uses_the_cache(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        $this->panel($manager, $lead)->assertOk();
        $this->panel($manager, $lead)->assertOk();
        $this->panel($this->userWithRole('Admin'), $lead)->assertOk();

        Http::assertSentCount(3); // customer, orders, invoices: once each
    }

    public function test_the_token_never_appears_in_the_panel_picker_or_lead_pages(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = $this->linked($manager);

        foreach ([
            $this->panel($manager, $lead)->getContent(),
            $this->actingAs($manager)->getJson('/leads/saims-customers?search=a')->getContent(),
            $this->actingAs($manager)->get("/leads/{$lead->id}")->getContent(),
            $this->actingAs($manager)->get("/leads/{$lead->id}/edit")->getContent(),
            $this->actingAs($manager)->get('/leads')->getContent(),
        ] as $body) {
            $this->assertStringNotContainsString(self::TOKEN, $body);
        }
    }

    public function test_only_reads_are_made_to_saims(): void
    {
        $this->fakeSaims();
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $this->panel($manager, $this->linked($manager))->assertOk();
        $this->actingAs($manager)->getJson('/leads/saims-customers?search=a')->assertOk();
        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['saims_customer_id' => 42]))->assertSessionHasNoErrors();

        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }
}
