<?php

namespace Tests\Feature\Pipeline;

use App\Enums\LeadPriority;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PipelineBoardTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private User $rep;

    private User $otherRep;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->rep = $this->userWithRole('Sales Rep');
        $this->otherRep = $this->userWithRole('Sales Rep');
        $this->manager = $this->userWithRole('Sales Manager');
    }

    private function lead(User $owner, LeadStage $stage, int $value = 1000, array $extra = []): Lead
    {
        return Lead::factory()->ownedBy($owner)->stage($stage)->create(['value' => $value, ...$extra]);
    }

    private function props(User $user, string $query = ''): array
    {
        return $this->actingAs($user)->get('/pipeline'.$query)->assertOk()->viewData('page')['props'];
    }

    /** @return array<string, array<string, mixed>> the columns keyed by stage */
    private function board(User $user, string $query = ''): array
    {
        return collect($this->props($user, $query)['board'])->keyBy('stage')->all();
    }

    private function ids(array $column): array
    {
        return array_column($column['cards'], 'id');
    }

    // ===================================================================== access

    public function test_guests_are_redirected_and_users_without_deals_view_are_denied(): void
    {
        $lead = $this->lead($this->manager, LeadStage::New);

        foreach (['/pipeline', "/pipeline/{$lead->id}", '/pipeline/column?stage=new&offset=0'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $nobody = User::factory()->create();
        foreach (['/pipeline', "/pipeline/{$lead->id}", '/pipeline/column?stage=new&offset=0'] as $url) {
            $this->actingAs($nobody)->get($url)->assertForbidden();
        }

        // leads.view alone is not enough: the Pipeline needs deals.view
        $leadsOnly = User::factory()->create();
        $leadsOnly->givePermissionTo('leads.view');
        $this->actingAs($leadsOnly)->get('/pipeline')->assertForbidden();
    }

    public function test_the_board_is_scoped_per_role(): void
    {
        $mine = $this->lead($this->rep, LeadStage::New);
        $theirs = $this->lead($this->otherRep, LeadStage::New);
        $managers = $this->lead($this->manager, LeadStage::Qualified);

        $rep = $this->board($this->rep);
        $this->assertSame([$mine->id], $this->ids($rep['new']));
        $this->assertSame([], $this->ids($rep['qualified']));

        foreach (['Sales Manager', 'Admin', 'Viewer'] as $role) {
            $all = $this->board($this->userWithRole($role));

            $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], $this->ids($all['new']), $role);
            $this->assertSame([$managers->id], $this->ids($all['qualified']), $role);
        }
    }

    public function test_there_are_five_columns_in_order_with_labels(): void
    {
        $board = $this->props($this->manager)['board'];

        $this->assertSame(['new', 'qualified', 'proposal', 'won', 'lost'], array_column($board, 'stage'));
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Won', 'Lost'], array_column($board, 'label'));
    }

    public function test_the_scope_follows_the_deals_permission_not_the_leads_one(): void
    {
        $mine = $this->lead($this->rep, LeadStage::New);
        $theirs = $this->lead($this->otherRep, LeadStage::New);

        // A viewer keeps leads.view-all but loses deals.view-all: the Leads list still shows everything ...
        $viewer = $this->userWithRole('Viewer');
        Role::findByName('Viewer')->revokePermissionTo('deals.view-all');

        $leads = $this->actingAs($viewer->fresh())->get('/leads')->viewData('page')['props']['leads']['data'];
        $this->assertCount(2, $leads);

        // ... but the board only shows the leads the viewer owns.
        $owned = $this->lead($viewer, LeadStage::Won);
        $board = $this->board($viewer->fresh());
        $this->assertSame([], $this->ids($board['new']));
        $this->assertSame([$owned->id], $this->ids($board['won']));

        // And the other way round: a rep who is given deals.view-all sees everything on the board.
        $this->rep->givePermissionTo('deals.view-all');
        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id, ], $this->ids($this->board($this->rep->fresh())['new']));
    }

    // ===================================================================== drawer

    public function test_a_card_opens_in_the_drawer_within_the_deals_scope(): void
    {
        $mine = $this->lead($this->rep, LeadStage::New, extra: ['name' => 'My Lead']);
        $theirs = $this->lead($this->otherRep, LeadStage::New);

        $props = $this->actingAs($this->rep)->get("/pipeline/{$mine->id}")->assertOk()->viewData('page')['props'];
        $this->assertSame('My Lead', $props['drawer']['lead']['name']);
        $this->assertArrayHasKey('board', $props, 'the board is still there behind the drawer');

        $this->actingAs($this->rep)->get("/pipeline/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->rep)->get('/pipeline/999999')->assertNotFound();
        $this->actingAs($this->userWithRole('Viewer'))->get("/pipeline/{$theirs->id}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/pipeline/{$mine->id}")->assertForbidden();
    }

    public function test_opening_a_card_can_skip_the_board_with_a_partial_visit(): void
    {
        $lead = $this->lead($this->manager, LeadStage::New);

        $version = $this->actingAs($this->manager)->get('/pipeline')->viewData('page')['version'];
        $props = $this->actingAs($this->manager)->get("/pipeline/{$lead->id}", [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Pipeline/Index',
            'X-Inertia-Partial-Data' => 'drawer',
        ])->assertOk()->json('props');

        $this->assertSame($lead->id, $props['drawer']['lead']['id']);
        $this->assertArrayNotHasKey('board', $props);
        $this->assertArrayNotHasKey('stats', $props);
    }

    // ====================================================================== money

    public function test_no_money_aggregates_without_the_revenue_permission(): void
    {
        $this->lead($this->rep, LeadStage::Proposal, 123457);
        $this->lead($this->rep, LeadStage::Won, 765433);

        foreach ([$this->rep, $this->userWithRole('Viewer')] as $user) {
            $props = $this->props($user);
            $json = json_encode($props);

            foreach ($props['board'] as $column) {
                $this->assertArrayNotHasKey('value', $column, 'no column totals in money');
            }
            foreach (['total_pipeline', 'open_value', 'won_value'] as $key) {
                $this->assertArrayNotHasKey($key, $props['stats']);
            }
            // the sums of the leads are nowhere (a single lead's own value is a card field, as in Leads)
            $this->assertStringNotContainsString('888890', $json); // 123457 + 765433
            $this->assertStringNotContainsString('Php', $json);
            $this->assertStringNotContainsString('$', $json);
        }
    }

    public function test_money_aggregates_are_present_with_the_revenue_permission(): void
    {
        $this->lead($this->manager, LeadStage::New, 1000);
        $this->lead($this->manager, LeadStage::New, 2000);
        $this->lead($this->manager, LeadStage::Won, 5000);

        $props = $this->props($this->manager);
        $board = collect($props['board'])->keyBy('stage');

        $this->assertEquals(3000, $board['new']['value']);
        $this->assertEquals(5000, $board['won']['value']);
        $this->assertEquals(0, $board['lost']['value']);
        $this->assertEquals(8000, $props['stats']['total_pipeline']);
        $this->assertEquals(3000, $props['stats']['open_value']);
        $this->assertEquals(5000, $props['stats']['won_value']);
        $this->assertStringNotContainsString('$', json_encode($props));
    }

    public function test_the_move_response_has_no_money_without_the_permission(): void
    {
        $lead = $this->lead($this->rep, LeadStage::New, 4000);

        $json = $this->actingAs($this->rep)->patchJson("/pipeline/{$lead->id}/move", ['stage' => 'won', 'position' => 0])->assertOk()->json();

        foreach ($json['columns'] as $column) {
            $this->assertArrayNotHasKey('value', $column);
        }
        $this->assertArrayNotHasKey('total_pipeline', $json['stats']);
        $this->assertArrayNotHasKey('won_value', $json['stats']);
    }

    // =================================================================== the stats

    public function test_the_stat_cards_and_column_totals_match_the_data(): void
    {
        foreach ([1000, 2000] as $v) {
            $this->lead($this->manager, LeadStage::New, $v);
        }
        $this->lead($this->manager, LeadStage::Qualified, 3000);
        $this->lead($this->manager, LeadStage::Proposal, 4000);
        foreach ([5000, 6000, 7000] as $v) {
            $this->lead($this->manager, LeadStage::Won, $v);
        }
        $this->lead($this->manager, LeadStage::Lost, 8000);

        $props = $this->props($this->manager);
        $stats = $props['stats'];

        $this->assertSame(8, $stats['total_leads']);
        $this->assertSame(4, $stats['open_deals'], 'new + qualified + proposal');
        $this->assertSame(75.0, $stats['win_rate'], '3 won / (3 won + 1 lost)');
        $this->assertEquals(36000, $stats['total_pipeline']);
        $this->assertEquals(10000, $stats['open_value']);
        $this->assertEquals(18000, $stats['won_value']);

        $counts = array_column($props['board'], 'count', 'stage');
        $this->assertSame(['new' => 2, 'qualified' => 1, 'proposal' => 1, 'won' => 3, 'lost' => 1], $counts);
    }

    public function test_the_stats_are_scoped_to_the_user(): void
    {
        $this->lead($this->rep, LeadStage::Won, 1000);
        $this->lead($this->rep, LeadStage::New, 500);
        $this->lead($this->otherRep, LeadStage::Lost, 99999);
        $this->rep->givePermissionTo('dashboard.view-revenue');

        $stats = $this->props($this->rep->fresh())['stats'];

        $this->assertSame(2, $stats['total_leads']);
        $this->assertSame(100.0, $stats['win_rate'], 'the other rep\'s lost lead is not counted');
        $this->assertEquals(1500, $stats['total_pipeline']);
    }

    public function test_an_empty_board_loads_with_zero_stats(): void
    {
        $props = $this->props($this->manager);

        $this->assertSame(0, $props['stats']['total_leads']);
        $this->assertSame(0.0, $props['stats']['win_rate']);
        $this->assertSame([0, 0, 0, 0, 0], array_column($props['board'], 'count'));
    }

    // ============================================================= limit and show more

    public function test_a_column_shows_30_cards_and_show_more_fetches_the_rest(): void
    {
        foreach (range(1, 35) as $i) {
            $this->lead($this->manager, LeadStage::New, $i);
        }

        $new = $this->board($this->manager)['new'];

        $this->assertCount(30, $new['cards']);
        $this->assertSame(35, $new['count'], 'the count is the whole column');
        $this->assertTrue($new['has_more']);

        $more = $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new&offset=30')->assertOk()->json();
        $this->assertCount(5, $more['cards']);
        $this->assertFalse($more['has_more']);

        // together they are the whole column once, in board order
        $all = array_merge($this->ids($new), array_column($more['cards'], 'id'));
        $this->assertCount(35, array_unique($all));
        $this->assertSame(Lead::where('stage', 'new')->orderBy('pipeline_position')->pluck('id')->all(), $all);
    }

    public function test_exactly_30_cards_have_no_show_more(): void
    {
        Lead::factory()->count(30)->ownedBy($this->manager)->stage(LeadStage::Lost)->create();

        $lost = $this->board($this->manager)['lost'];

        $this->assertCount(30, $lost['cards']);
        $this->assertFalse($lost['has_more']);
    }

    public function test_show_more_is_scoped_filtered_and_validated(): void
    {
        foreach (range(1, 33) as $i) {
            $this->lead($this->manager, LeadStage::New, 1, ['priority' => LeadPriority::High]);
        }
        $mine = $this->lead($this->rep, LeadStage::New, 1, ['priority' => LeadPriority::Low, 'name' => 'Rep Lead']);

        // a rep only ever gets their own cards from the column endpoint
        $json = $this->actingAs($this->rep)->getJson('/pipeline/column?stage=new&offset=0')->assertOk()->json();
        $this->assertSame([$mine->id], array_column($json['cards'], 'id'));

        // the filter applies to the next page too
        $json = $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new&offset=30&priority=high')->json();
        $this->assertCount(3, $json['cards']);
        $json = $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new&offset=0&priority=low&search=Rep')->json();
        $this->assertSame([$mine->id], array_column($json['cards'], 'id'));

        $this->actingAs($this->manager)->getJson('/pipeline/column?stage=bogus&offset=0')->assertStatus(422);
        $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new&offset=-1')->assertStatus(422);
        $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new')->assertStatus(422);
        $this->actingAs($this->manager)->getJson('/pipeline/column?stage=new&offset=0&priority=urgent')->assertStatus(422);
        $this->actingAs(User::factory()->create())->getJson('/pipeline/column?stage=new&offset=0')->assertForbidden();
    }

    // ======================================================== search and priority

    public function test_search_and_priority_filter_the_cards_counts_and_totals_within_the_scope(): void
    {
        $quiet = ['company' => 'Zzz Corp', 'email' => null];
        $a = $this->lead($this->rep, LeadStage::New, 100, [...$quiet, 'name' => 'Anna Reyes', 'priority' => LeadPriority::High]);
        $this->lead($this->rep, LeadStage::New, 200, [...$quiet, 'name' => 'Anna Cruz', 'priority' => LeadPriority::Low]);
        $this->lead($this->rep, LeadStage::Won, 300, [...$quiet, 'name' => 'Bob', 'priority' => LeadPriority::High]);
        $this->lead($this->otherRep, LeadStage::New, 400, [...$quiet, 'name' => 'Anna Someone Else', 'priority' => LeadPriority::High]);

        $board = $this->board($this->rep, '?search=anna&priority=high');

        $this->assertSame([$a->id], $this->ids($board['new']), 'only own leads, matching both filters');
        $this->assertSame(1, $board['new']['count']);
        $this->assertSame([], $this->ids($board['won']));

        // the stat cards ignore the filters
        $this->assertSame(3, $this->props($this->rep, '?search=anna&priority=high')['stats']['total_leads']);

        // a manager sees the other rep's match too, and the column totals follow the filter
        $managerBoard = $this->board($this->manager, '?search=anna&priority=high');
        $this->assertSame(2, $managerBoard['new']['count']);
        $this->assertEquals(500, $managerBoard['new']['value']);

        // unknown filter values are ignored
        $this->assertCount(2, $this->board($this->rep, '?priority=bogus')['new']['cards']);
    }

    public function test_a_search_with_wildcard_characters_is_literal(): void
    {
        $quiet = ['company' => 'Zzz Corp', 'email' => null];
        $this->lead($this->manager, LeadStage::New, 1, [...$quiet, 'name' => 'Plain']);
        $match = $this->lead($this->manager, LeadStage::New, 1, [...$quiet, 'name' => '100% Sure']);

        $board = $this->board($this->manager, '?search='.urlencode('%'));

        $this->assertSame([$match->id], $this->ids($board['new']));
    }

    // ======================================================== other props

    public function test_the_page_tells_the_frontend_whether_cards_can_be_moved(): void
    {
        $this->assertTrue($this->props($this->manager)['canMove']);
        $this->assertTrue($this->props($this->rep)['canMove']);
        $this->assertFalse($this->props($this->userWithRole('Viewer'))['canMove']);
    }

    public function test_cards_carry_only_what_a_card_shows(): void
    {
        $this->lead($this->manager, LeadStage::New, 5000, ['email' => 'private@example.com', 'notes' => 'secret notes', 'phone' => '0999']);

        $card = $this->board($this->manager)['new']['cards'][0];

        $this->assertSame(['id', 'name', 'company', 'value', 'priority', 'stage', 'position'], array_keys($card));
        $this->assertStringNotContainsString('private@example.com', json_encode($this->props($this->manager)['board']));
        $this->assertStringNotContainsString('secret notes', json_encode($this->props($this->manager)['board']));
    }
}
