<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\Feature\Saims\SaimsClientTest;
use Tests\TestCase;

/**
 * The dashboard on real data: scoped to the user, limited by the date range,
 * money only with dashboard.view-revenue, SAIMS revenue only with saims.invoices.
 */
class DashboardTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private const TOKEN = SaimsClientTest::TOKEN;

    private const REVENUE = ['pipeline', 'revenueSource', 'weeklyRevenue', 'revenueGoal'];

    private const LEADS = ['conversion', 'engagement', 'activity', 'sources'];

    private const DEALS = ['pipelineStages', 'topOpenDeals'];

    private const WIDGETS = [
        'pipeline', 'weeklyRevenue', 'revenueGoal',
        'conversion', 'engagement', 'activity', 'sources',
        'topContacts', 'pipelineStages', 'topOpenDeals',
        'followUps', 'insights',
    ];

    private User $rep;

    private User $otherRep;

    private User $manager;

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
        ], 'crm.revenue_target' => null]);

        $this->rep = $this->userWithRole('Sales Rep');
        $this->otherRep = $this->userWithRole('Sales Rep');
        $this->manager = $this->userWithRole('Sales Manager');
    }

    // ------------------------------------------------------------------- helpers

    private function lead(User $owner, LeadStage $stage, int $value, string $createdAt = 'now', array $extra = []): Lead
    {
        $created = $createdAt === 'now' ? CarbonImmutable::now() : CarbonImmutable::parse($createdAt);

        return Lead::factory()->ownedBy($owner)->stage($stage)->create([
            'value' => $value,
            'created_at' => $created,
            'updated_at' => $created,
            ...$extra,
        ]);
    }

    /** Initial props (what the page opens with). */
    private function props(User $user, string $query = ''): array
    {
        return $this->actingAs($user)->get('/dashboard'.$query)->assertOk()->viewData('page')['props'];
    }

    /** The deferred SAIMS widgets, loaded the way the browser does: a partial reload. */
    private function deferred(User $user, string $query = ''): array
    {
        $version = $this->actingAs($user)->get('/dashboard'.$query)->viewData('page')['version'];

        return $this->actingAs($user)->get('/dashboard'.$query, [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'weeklyRevenue,revenueGoal',
        ])->assertOk()->json('props');
    }

    private function widgetsIn(array $props): array
    {
        return array_values(array_intersect(self::WIDGETS, array_keys($props)));
    }

    private function invoice(int $id, string $date, string $total, bool $cancelled = false): array
    {
        return ['id' => $id, 'sales_no' => "INV-{$id}", 'date' => $date, 'cancelled' => $cancelled, 'customer' => ['id' => 1, 'name' => 'ACME'], 'total_sales' => $total, 'amount_due' => '1.00', 'amount_paid' => '0.00', 'lines' => []];
    }

    /** @param list<array<string, mixed>> $invoices */
    private function fakeInvoices(array $invoices, int $lastPage = 1): void
    {
        Http::fake(['*/api/v1/invoices*' => Http::response([
            'data' => $invoices,
            'meta' => ['current_page' => 1, 'last_page' => $lastPage, 'per_page' => 100, 'total' => count($invoices) * $lastPage],
        ])]);
    }

    private function invoiceCalls(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), '/invoices'))->count();
    }

    // ====================================================================== access

    public function test_guests_are_redirected_and_users_without_dashboard_view_are_denied(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();

        $viewer = $this->userWithRole('Viewer');
        Role::findByName('Viewer')->revokePermissionTo('dashboard.view');
        $this->actingAs($viewer->fresh())->get('/dashboard')->assertForbidden();
    }

    public static function widgetsByRole(): array
    {
        $all = [...self::LEADS, ...self::DEALS, 'topContacts', 'followUps', 'insights'];

        return [
            'Admin' => ['Admin', [...$all, 'pipeline', 'revenueSource'], true],
            'Sales Manager' => ['Sales Manager', [...$all, 'pipeline', 'revenueSource'], true],
            'Sales Rep' => ['Sales Rep', $all, false],
            'Viewer' => ['Viewer', [...self::LEADS, ...self::DEALS, 'topContacts', 'followUps'], false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetsByRole')]
    public function test_each_role_receives_exactly_its_widgets(string $role, array $expectedInitial, bool $hasSaimsRevenue): void
    {
        Http::fake();
        $user = $this->userWithRole($role);

        $props = $this->props($user);

        $this->assertEqualsCanonicalizing(
            [...$expectedInitial, 'period'],
            array_values(array_intersect([...self::WIDGETS, 'revenueSource', 'period'], array_keys($props))),
        );

        // The SAIMS revenue widgets are not in the first load at all: they are deferred.
        $this->assertArrayNotHasKey('weeklyRevenue', $props);
        $this->assertArrayNotHasKey('revenueGoal', $props);

        if ($hasSaimsRevenue) {
            $this->assertSame('saims', $props['revenueSource']);
        } else {
            $this->assertArrayNotHasKey('revenueSource', $props);
        }

        Http::assertNothingSent(); // opening the dashboard calls SAIMS for nobody
    }

    public function test_the_deferred_widgets_exist_only_for_users_who_may_see_them(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);

        foreach (['Admin', 'Sales Manager'] as $role) {
            $deferred = $this->deferred($this->userWithRole($role));
            $this->assertNotNull($deferred['weeklyRevenue'] ?? null, $role);
            $this->assertNotNull($deferred['revenueGoal'] ?? null, $role);
        }

        foreach (['Sales Rep', 'Viewer'] as $role) {
            $deferred = $this->deferred($this->userWithRole($role));
            $this->assertNull($deferred['weeklyRevenue'] ?? null);
            $this->assertNull($deferred['revenueGoal'] ?? null);
        }
    }

    // ===================================================================== scope

    public function test_a_reps_numbers_only_include_their_own_leads(): void
    {
        $this->lead($this->rep, LeadStage::New, 1000);
        $this->lead($this->rep, LeadStage::Won, 2000);
        $this->lead($this->otherRep, LeadStage::Won, 9000, extra: ['name' => 'Someone Elses Lead']);
        $this->lead($this->manager, LeadStage::Proposal, 8000, extra: ['name' => 'Managers Lead']);

        $rep = $this->props($this->rep);

        $this->assertSame(2, $rep['conversion']['leads']);
        $this->assertSame(1, $rep['conversion']['won']);
        $this->assertSame(2, $rep['sources']['total']);
        $this->assertSame(2, $rep['pipelineStages']['total']);
        $this->assertSame(2, array_sum(array_column($rep['engagement']['monthly']['bars'], 'value')));
        $this->assertCount(2, $rep['activity']);
        $this->assertStringNotContainsString('Someone Elses Lead', json_encode($rep));
        $this->assertStringNotContainsString('Managers Lead', json_encode($rep));
        $this->assertCount(1, $rep['topOpenDeals'], 'only their own open lead');

        // view-all roles see everything
        foreach (['Sales Manager', 'Admin', 'Viewer'] as $role) {
            $all = $this->props($this->userWithRole($role));

            $this->assertSame(4, $all['conversion']['leads'], $role);
            $this->assertSame(4, $all['sources']['total'], $role);
            $this->assertSame(4, $all['pipelineStages']['total'], $role);
        }
    }

    public function test_money_aggregates_are_scoped_too(): void
    {
        $this->lead($this->rep, LeadStage::New, 1000);
        $this->lead($this->otherRep, LeadStage::New, 50000);
        $this->rep->givePermissionTo('dashboard.view-revenue');

        $props = $this->props($this->rep->fresh());

        $this->assertEquals(1000, $props['pipeline']['total']);
        $this->assertSame(1, $props['pipeline']['deals_count']);
    }

    // ====================================================================== range

    public function test_the_range_defaults_to_the_current_year_and_filters_by_created_at(): void
    {
        $year = now()->year;
        $this->lead($this->manager, LeadStage::New, 100, "{$year}-02-10");
        $this->lead($this->manager, LeadStage::New, 100, "{$year}-09-10");
        $this->lead($this->manager, LeadStage::New, 100, ($year - 1).'-06-10');

        $default = $this->props($this->manager);
        $this->assertSame(['from' => "{$year}-01-01", 'to' => "{$year}-12-31", 'is_default' => true], $default['period']);
        $this->assertSame(2, $default['conversion']['leads']);

        $narrow = $this->props($this->manager, "?from={$year}-02-01&to={$year}-02-28");
        $this->assertSame(['from' => "{$year}-02-01", 'to' => "{$year}-02-28", 'is_default' => false], $narrow['period']);
        $this->assertSame(1, $narrow['conversion']['leads']);
        $this->assertSame(1, $narrow['sources']['total']);
        $this->assertSame(1, $narrow['pipelineStages']['total']);

        $lastYear = $this->props($this->manager, '?from='.($year - 1).'-01-01&to='.($year - 1).'-12-31');
        $this->assertSame(1, $lastYear['conversion']['leads']);
    }

    public function test_the_range_end_is_inclusive_to_the_last_second(): void
    {
        $this->lead($this->manager, LeadStage::New, 100, '2026-03-31 23:59:00');
        $this->lead($this->manager, LeadStage::New, 100, '2026-04-01 00:01:00');

        $props = $this->props($this->manager, '?from=2026-03-01&to=2026-03-31');

        $this->assertSame(1, $props['conversion']['leads']);
    }

    public static function badRanges(): array
    {
        return [
            'only from' => ['?from=2026-01-01'],
            'only to' => ['?to=2026-01-31'],
            'bad format' => ['?from=01/01/2026&to=31/01/2026'],
            'not a date' => ['?from=abc&to=def'],
            'to before from' => ['?from=2026-05-10&to=2026-05-01'],
            'longer than five years' => ['?from=2015-01-01&to=2026-01-01'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badRanges')]
    public function test_a_bad_range_is_rejected(string $query): void
    {
        Http::fake();

        $this->actingAs($this->manager)->from('/dashboard')->get('/dashboard'.$query)->assertSessionHasErrors();

        Http::assertNothingSent();
    }

    public function test_the_previous_period_drives_the_change(): void
    {
        $year = now()->year;
        // this year: won 3, lost 1 => 75%
        foreach (range(1, 3) as $i) {
            $this->lead($this->manager, LeadStage::Won, 100, "{$year}-03-0{$i}");
        }
        $this->lead($this->manager, LeadStage::Lost, 100, "{$year}-03-05");
        // last year: won 1, lost 1 => 50%
        $this->lead($this->manager, LeadStage::Won, 100, ($year - 1).'-03-01');
        $this->lead($this->manager, LeadStage::Lost, 100, ($year - 1).'-03-02');

        $conversion = $this->props($this->manager)['conversion'];

        $this->assertSame(75.0, $conversion['win_rate']);
        $this->assertSame(25.0, $conversion['change_percent']);
    }

    // =================================================================== numbers

    public function test_conversion_is_won_over_won_plus_lost_with_open_leads_instead_of_tasks(): void
    {
        $this->lead($this->manager, LeadStage::Won, 100);
        $this->lead($this->manager, LeadStage::Lost, 100);
        $this->lead($this->manager, LeadStage::Lost, 100);
        $this->lead($this->manager, LeadStage::New, 100);
        $this->lead($this->manager, LeadStage::Qualified, 100);
        $this->lead($this->manager, LeadStage::Proposal, 100);

        $conversion = $this->props($this->manager)['conversion'];

        $this->assertSame(33.3, $conversion['win_rate'], '1 won / (1 won + 2 lost)');
        $this->assertSame(6, $conversion['leads']);
        $this->assertSame(3, $conversion['open_leads']);
        $this->assertNull($conversion['change_percent'], 'no closed leads in the previous period');
        $this->assertArrayNotHasKey('open_tasks', $conversion);
    }

    public function test_with_no_closed_leads_the_win_rate_is_zero_not_an_error(): void
    {
        $this->lead($this->manager, LeadStage::New, 100);

        $this->assertSame(0.0, $this->props($this->manager)['conversion']['win_rate']);
        $this->assertSame(0.0, $this->props($this->userWithRole('Admin'), '?from=2020-01-01&to=2020-12-31')['conversion']['win_rate']);
    }

    public function test_pipeline_goal_is_the_value_of_the_open_leads(): void
    {
        $this->lead($this->manager, LeadStage::New, 1000);
        $this->lead($this->manager, LeadStage::Qualified, 2000);
        $this->lead($this->manager, LeadStage::Proposal, 3000);
        $this->lead($this->manager, LeadStage::Won, 99999);
        $this->lead($this->manager, LeadStage::Lost, 88888);

        $pipeline = $this->props($this->manager)['pipeline'];

        $this->assertEquals(6000, $pipeline['total']);
        $this->assertSame(3, $pipeline['deals_count']);
    }

    public function test_pipeline_by_stage_lists_every_stage_with_counts_values_and_percent(): void
    {
        $this->lead($this->manager, LeadStage::New, 1000);
        $this->lead($this->manager, LeadStage::New, 1000);
        $this->lead($this->manager, LeadStage::Won, 6000);

        $stages = $this->props($this->manager)['pipelineStages'];

        $this->assertSame(3, $stages['total']);
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Won', 'Lost'], array_column($stages['items'], 'stage'));
        $new = $stages['items'][0];
        $this->assertSame(2, $new['count']);
        $this->assertEquals(2000, $new['value']);
        $this->assertSame(25.0, $new['percent'], 'by value: 2000 of 8000 (the manager sees money)');
        $this->assertSame(0, $stages['items'][1]['count']);
        $this->assertEquals(0, $stages['items'][1]['value']);

        // without revenue: counts and a percent of the lead COUNT, no peso value
        $viewer = $this->props($this->userWithRole('Viewer'))['pipelineStages']['items'];
        $this->assertSame(66.7, $viewer[0]['percent'], '2 of 3 leads');
        $this->assertArrayNotHasKey('value', $viewer[0]);
    }

    public function test_top_open_deals_are_the_five_biggest_open_leads(): void
    {
        foreach ([100, 600, 300, 900, 200, 700, 400] as $i => $value) {
            $this->lead($this->manager, LeadStage::Proposal, $value, extra: ['name' => "Deal {$value}"]);
        }
        $this->lead($this->manager, LeadStage::Won, 5000, extra: ['name' => 'Closed Won']);
        $this->lead($this->manager, LeadStage::Lost, 6000, extra: ['name' => 'Closed Lost']);

        $deals = $this->props($this->manager)['topOpenDeals'];

        $this->assertSame(['Deal 900', 'Deal 700', 'Deal 600', 'Deal 400', 'Deal 300'], array_column($deals, 'name'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($deals, 'rank'));
        $this->assertEquals(900, $deals[0]['value']);
        $this->assertSame('Proposal', $deals[0]['stage']);
    }

    public function test_lead_activity_lists_the_latest_changes_first(): void
    {
        $old = $this->lead($this->manager, LeadStage::New, 100, extra: ['name' => 'Old']);
        $recent = $this->lead($this->manager, LeadStage::Qualified, 200, extra: ['name' => 'Recent']);
        Lead::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(3)]);
        Lead::query()->whereKey($recent->id)->update(['updated_at' => now()->subHour()]);

        $activity = $this->props($this->manager)['activity'];

        $this->assertSame(['Recent', 'Old'], array_column($activity, 'name'));
        $this->assertSame('Qualified', $activity[0]['status']);
        $this->assertSame(now()->subHour()->toDateString(), $activity[0]['date']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $activity[0]['time']);

        foreach ($this->props($this->userWithRole('Viewer'))['activity'] as $row) {
            $this->assertArrayNotHasKey('value', $row);
        }
    }

    public function test_lead_activity_is_capped_at_six(): void
    {
        Lead::factory()->count(9)->ownedBy($this->manager)->create();

        $this->assertCount(6, $this->props($this->manager)['activity']);
    }

    public function test_leads_by_source_are_counted_and_sorted(): void
    {
        $this->lead($this->manager, LeadStage::New, 1, extra: ['source' => LeadSource::Referral]);
        $this->lead($this->manager, LeadStage::New, 1, extra: ['source' => LeadSource::Website]);
        $this->lead($this->manager, LeadStage::New, 1, extra: ['source' => LeadSource::Website]);
        $this->lead($this->manager, LeadStage::New, 1, extra: ['source' => LeadSource::SocialMedia]);
        $this->lead($this->manager, LeadStage::New, 1, extra: ['source' => LeadSource::Website]);

        $sources = $this->props($this->manager)['sources'];

        $this->assertSame(5, $sources['total']);
        $this->assertSame([['label' => 'Website', 'value' => 3], ['label' => 'Referral', 'value' => 1], ['label' => 'Social media', 'value' => 1]], array_slice($sources['items'], 0, 3));
    }

    public function test_engagement_counts_new_leads_per_month_and_per_year(): void
    {
        $year = now()->year;
        $this->lead($this->manager, LeadStage::New, 1, "{$year}-01-05");
        $this->lead($this->manager, LeadStage::New, 1, "{$year}-01-20");
        $this->lead($this->manager, LeadStage::New, 1, "{$year}-03-02");
        $this->lead($this->manager, LeadStage::New, 1, ($year - 2).'-07-01');

        $engagement = $this->props($this->manager)['engagement'];

        $monthly = array_column($engagement['monthly']['bars'], 'value', 'label');
        $this->assertSame(12, count($engagement['monthly']['bars']));
        $this->assertSame(2, $monthly['Jan']);
        $this->assertSame(0, $monthly['Feb']);
        $this->assertSame(1, $monthly['Mar']);
        $this->assertSame(now()->month - 1, $engagement['monthly']['highlight'], 'the current month is highlighted');
        $this->assertSame(0.0, $engagement['monthly']['bars'][0]['change_percent'], 'the first bar has nothing to compare with');

        $yearly = array_column($engagement['annually']['bars'], 'value', 'label');
        $this->assertSame(range($year - 4, $year), array_map('intval', array_keys($yearly)));
        $this->assertSame(3, $yearly[(string) $year]);
        $this->assertSame(1, $yearly[(string) ($year - 2)]);

        // the monthly bars follow the range; the yearly ones do not
        $march = $this->props($this->manager, "?from={$year}-03-01&to={$year}-03-31")['engagement'];
        $this->assertSame([['label' => 'Mar', 'value' => 1]], array_map(fn ($b) => ['label' => $b['label'], 'value' => $b['value']], $march['monthly']['bars']));
        $this->assertSame(3, array_column($march['annually']['bars'], 'value', 'label')[(string) $year]);
    }

    public function test_an_empty_dashboard_still_loads(): void
    {
        $props = $this->props($this->manager);

        $this->assertSame(0, $props['conversion']['leads']);
        $this->assertSame([], $props['activity']);
        $this->assertSame([], $props['topOpenDeals']);
        $this->assertSame([], $props['sources']['items']);
        $this->assertSame(0, $props['pipelineStages']['total']);
    }

    // ===================================================================== money

    public function test_no_money_without_the_revenue_permission(): void
    {
        $this->lead($this->rep, LeadStage::Proposal, 123457);
        $this->lead($this->rep, LeadStage::Won, 765433);
        Http::fake();

        foreach (['Sales Rep', 'Viewer'] as $role) {
            $user = $role === 'Sales Rep' ? $this->rep : $this->userWithRole('Viewer');
            $props = $this->props($user);
            $json = json_encode($props);

            foreach (['pipeline', 'weeklyRevenue', 'revenueGoal', 'revenueSource'] as $key) {
                $this->assertArrayNotHasKey($key, $props, "{$role} must not receive {$key}");
            }
            foreach ($props['activity'] as $row) {
                $this->assertArrayNotHasKey('value', $row);
            }
            foreach ($props['pipelineStages']['items'] as $stage) {
                $this->assertArrayNotHasKey('value', $stage);
            }
            foreach ($props['topOpenDeals'] as $deal) {
                $this->assertArrayNotHasKey('value', $deal);
            }
            $this->assertStringNotContainsString('123457', $json);
            $this->assertStringNotContainsString('765433', $json);
            $this->assertStringNotContainsString('Php', $json);
            $this->assertStringNotContainsString('$', $json);

            $deferred = $this->deferred($user);
            $this->assertNull($deferred['weeklyRevenue'] ?? null);
            $this->assertNull($deferred['revenueGoal'] ?? null);
        }

        Http::assertNothingSent();
    }

    public function test_money_is_present_with_the_revenue_permission(): void
    {
        $this->lead($this->manager, LeadStage::Proposal, 5000);
        Http::fake();

        $props = $this->props($this->manager);

        $this->assertEquals(5000, $props['activity'][0]['value']);
        $this->assertEquals(5000, $props['pipelineStages']['items'][2]['value']);
        $this->assertEquals(5000, $props['topOpenDeals'][0]['value']);
        $this->assertStringNotContainsString('$', json_encode($props));
    }

    public function test_saims_revenue_needs_saims_invoices_on_top_of_the_revenue_permission(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);

        // saims.invoices but NO dashboard.view-revenue: no revenue at all, and no SAIMS call
        $repWithInvoices = $this->userWithRole('Sales Rep');
        $repWithInvoices->givePermissionTo('saims.invoices');
        $props = $this->props($repWithInvoices);
        $this->assertArrayNotHasKey('revenueSource', $props);
        $deferred = $this->deferred($repWithInvoices);
        $this->assertNull($deferred['weeklyRevenue'] ?? null);
        $this->assertNull($deferred['revenueGoal'] ?? null);
        Http::assertNothingSent();

        // dashboard.view-revenue but NO saims.invoices: lead-based fallback, no SAIMS call
        $repWithRevenue = $this->userWithRole('Sales Rep');
        $repWithRevenue->givePermissionTo('dashboard.view-revenue');
        $props = $this->props($repWithRevenue);
        $this->assertSame('leads', $props['revenueSource']);
        $this->assertSame('leads', $props['weeklyRevenue']['source']);
        $this->assertSame('leads', $props['revenueGoal']['source']);
        $this->assertSame('Won lead value', $props['revenueGoal']['label']);
        $this->assertNotEmpty($props['revenueGoal']['note']);
        Http::assertNothingSent();
    }

    public function test_the_lead_based_fallback_sums_won_leads_in_scope(): void
    {
        $this->rep->givePermissionTo('dashboard.view-revenue');
        $this->lead($this->rep, LeadStage::Won, 5000);
        $this->lead($this->rep, LeadStage::Won, 2500);
        $this->lead($this->rep, LeadStage::New, 99999);
        $this->lead($this->otherRep, LeadStage::Won, 70000);

        $props = $this->props($this->rep->fresh());

        $this->assertEquals(7500, $props['revenueGoal']['amount']);
        $this->assertEquals(7500, $props['weeklyRevenue']['amount'], 'won in the last 7 days');
        $this->assertCount(12, $props['revenueGoal']['series']);
        $this->assertEquals(7500, array_sum(array_column($props['revenueGoal']['series'], 'value')));
    }

    // =============================================================== SAIMS revenue

    public function test_invoiced_revenue_sums_total_sales_of_non_cancelled_invoices(): void
    {
        $month = now()->format('Y-m');
        $this->fakeInvoices([
            $this->invoice(1, "{$month}-01", '1000.00'),
            $this->invoice(2, "{$month}-02", '500.50'),
            $this->invoice(3, "{$month}-03", '99999.00', cancelled: true),
        ]);

        $deferred = $this->deferred($this->manager);

        $goal = $deferred['revenueGoal'];
        $this->assertSame('saims', $goal['source']);
        $this->assertSame('Invoiced (incl. VAT)', $goal['label']);
        $this->assertEquals(1500.50, $goal['amount'], 'total_sales, cancelled left out');
        $this->assertSame(2, $goal['invoices']);
        $this->assertFalse($goal['partial']);
        $this->assertNull($goal['error']);
        $this->assertCount(12, $goal['series']);
        $series = array_column($goal['series'], 'value', 'label');
        $this->assertEquals(1500.50, $series[now()->format('M')]);
    }

    public function test_the_revenue_target_is_optional(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);

        $this->assertNull($this->deferred($this->manager)['revenueGoal']['target']);

        config(['crm.revenue_target' => 5000000.0]);
        cache()->flush();
        $this->assertEquals(5000000, $this->deferred($this->manager)['revenueGoal']['target']);
    }

    public function test_weekly_revenue_is_the_last_seven_days_against_the_seven_before(): void
    {
        $this->fakeInvoices([
            $this->invoice(1, now()->toDateString(), '300.00'),
            $this->invoice(2, now()->subDays(6)->toDateString(), '100.00'),     // still this week
            $this->invoice(3, now()->subDays(7)->toDateString(), '200.00'),     // the week before
            $this->invoice(4, now()->subDays(12)->toDateString(), '200.00'),    // the week before
            $this->invoice(5, now()->subDays(2)->toDateString(), '50.00', cancelled: true),
        ]);

        $weekly = $this->deferred($this->manager)['weeklyRevenue'];

        $this->assertEquals(400, $weekly['amount']);
        $this->assertEquals(0, $weekly['change_percent']);
        $this->assertSame('Invoiced (incl. VAT)', $weekly['label']);
    }

    public function test_weekly_revenue_ignores_the_selected_range(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '300.00')]);

        $this->deferred($this->manager, '?from=2020-01-01&to=2020-12-31');

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return ($q['from'] ?? null) === now()->subDays(13)->toDateString() && ($q['to'] ?? null) === now()->toDateString();
        });
    }

    public function test_the_invoice_requests_are_whitelisted_and_use_a_page_size_of_100(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '1.00')]);

        $this->deferred($this->manager, '?from=2026-01-01&to=2026-06-30&evil=1');

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            ksort($q);

            return str_contains($request->url(), '/invoices') && ($q['from'] ?? null) === '2026-01-01'
                && $q === ['from' => '2026-01-01', 'page' => '1', 'per_page' => '100', 'to' => '2026-06-30'];
        });
    }

    public function test_at_most_ten_pages_are_read_and_the_result_is_marked_partial(): void
    {
        $month = now()->format('Y-m');
        $this->fakeInvoices([$this->invoice(1, "{$month}-01", '100.00')], lastPage: 25);

        $goal = $this->deferred($this->manager, "?from={$month}-01&to={$month}-28")['revenueGoal'];

        $this->assertTrue($goal['partial']);
        $this->assertEquals(1000, $goal['amount'], '10 pages of one 100.00 invoice');
        $this->assertNull($goal['change_percent'], 'no comparison with a partial total');

        $pagesForTheRange = Http::recorded(fn (Request $request) => str_contains($request->url(), "from={$month}-01"))->count();
        $this->assertSame(10, $pagesForTheRange, 'the hard cap of 10 pages');
    }

    public function test_the_aggregate_is_cached_for_ten_minutes_even_without_the_page_cache(): void
    {
        config(['services.saims.cache_seconds' => 0]); // the per-request cache is off
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);

        $this->deferred($this->manager);
        $after = $this->invoiceCalls();
        $this->assertGreaterThan(0, $after);

        $this->deferred($this->manager);
        $this->deferred($this->userWithRole('Admin'));

        $this->assertSame($after, $this->invoiceCalls(), 'reloading the dashboard does not read SAIMS again');
    }

    public function test_the_change_against_the_previous_period_is_computed_when_possible(): void
    {
        $year = now()->year;
        Http::fake(function (Request $request) use ($year) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $thisYear = str_starts_with((string) ($q['from'] ?? ''), (string) $year);

            return Http::response([
                'data' => [$this->invoice($thisYear ? 1 : 2, ($thisYear ? $year : $year - 1).'-05-05', $thisYear ? '300.00' : '200.00')],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 100, 'total' => 1],
            ]);
        });

        $goal = $this->deferred($this->manager, "?from={$year}-01-01&to={$year}-12-30")['revenueGoal'];

        $this->assertEquals(300, $goal['amount']);
        $this->assertEquals(50, $goal['change_percent'], '300 against 200');
    }

    // ============================================================ failure isolation

    public function test_a_saims_failure_only_affects_the_saims_widgets(): void
    {
        $this->lead($this->manager, LeadStage::Won, 100);
        Http::fake(['*' => function () {
            throw new ConnectionException('refused '.self::TOKEN);
        }]);

        // the page itself opens fine, with every other widget
        $props = $this->props($this->manager);
        $this->assertSame(1, $props['conversion']['leads']);
        $this->assertNotEmpty($props['pipeline']);

        // the deferred SAIMS widgets carry a friendly error, not an exception
        $deferred = $this->deferred($this->manager);
        foreach (['weeklyRevenue', 'revenueGoal'] as $key) {
            $this->assertSame('saims', $deferred[$key]['source']);
            $this->assertSame('unreachable', $deferred[$key]['error']['code']);
            $this->assertNotEmpty($deferred[$key]['error']['message']);
        }
        $this->assertStringNotContainsString(self::TOKEN, json_encode($deferred));
    }

    public function test_each_saims_error_status_isolates_the_widget(): void
    {
        $status = 500;
        Http::fake(function () use (&$status) {
            return Http::response(['detail' => 'leaked '.self::TOKEN], $status);
        });

        foreach ([401 => 'token_rejected', 403 => 'forbidden', 429 => 'rate_limited', 500 => 'failed'] as $status => $code) {
            cache()->flush();

            $this->actingAs($this->manager)->get('/dashboard')->assertOk();
            $deferred = $this->deferred($this->manager);

            $this->assertSame($code, $deferred['revenueGoal']['error']['code']);
            $this->assertSame($code, $deferred['weeklyRevenue']['error']['code']);
            $this->assertStringNotContainsString('leaked', json_encode($deferred));
            $this->assertStringNotContainsString(self::TOKEN, json_encode($deferred));
        }
    }

    public function test_an_unconfigured_saims_shows_an_error_widget_and_calls_nothing(): void
    {
        config(['services.saims.token' => '']);
        Http::fake();

        $deferred = $this->deferred($this->manager);

        $this->assertSame('not_configured', $deferred['revenueGoal']['error']['code']);
        Http::assertNothingSent();
    }

    // ================================================================== other rules

    public function test_the_placeholders_stay_behind_their_permissions(): void
    {
        foreach ([
            ['Viewer', 'insights', false],
            ['Sales Rep', 'insights', true],
            ['Viewer', 'topContacts', true],
            ['Viewer', 'followUps', true],
        ] as [$role, $key, $present]) {
            $this->assertSame($present, array_key_exists($key, $this->props($this->userWithRole($role))), "{$role} {$key}");
        }

        Role::findByName('Viewer')->revokePermissionTo(['contacts.view', 'tasks.view']);
        $props = $this->props($this->userWithRole('Viewer'));
        $this->assertArrayNotHasKey('topContacts', $props);
        $this->assertArrayNotHasKey('followUps', $props);
    }

    public function test_the_new_widgets_follow_their_own_permissions_immediately(): void
    {
        $this->lead($this->manager, LeadStage::New, 1);
        $viewer = $this->userWithRole('Viewer');

        $this->assertContains('conversion', $this->widgetsIn($this->props($viewer)) + ['conversion']);

        Role::findByName('Viewer')->revokePermissionTo(['leads.view', 'deals.view']);
        $widgets = $this->widgetsIn($this->props($viewer->fresh()));

        foreach ([...self::LEADS, ...self::DEALS] as $key) {
            $this->assertNotContains($key, $widgets);
        }
    }

    public function test_the_token_and_other_users_never_appear_in_any_prop(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);
        $other = $this->userWithRole('Admin');

        foreach ([$this->manager, $other, $this->rep] as $user) {
            $json = json_encode([$this->props($user), $this->deferred($user)]);

            $this->assertStringNotContainsString(self::TOKEN, $json);
            $this->assertStringNotContainsString($user->is($this->manager) ? $other->email : $this->manager->email, $json);
            $this->assertStringNotContainsString($user->password, $json);
            $this->assertStringNotContainsString('remember_token', $json);
        }
    }

    public function test_the_dashboard_never_writes_to_saims(): void
    {
        $this->fakeInvoices([$this->invoice(1, now()->toDateString(), '100.00')]);

        $this->deferred($this->manager);
        $this->deferred($this->userWithRole('Admin'), '?from=2026-01-01&to=2026-03-31');

        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }
}
