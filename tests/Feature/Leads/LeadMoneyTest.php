<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class LeadMoneyTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private const MONEY_KEYS = ['open_pipeline', 'won_value', 'avg_deal_size'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    private function stats(User $user): array
    {
        return $this->actingAs($user)->get('/leads')->assertOk()->viewData('page')['props']['stats'];
    }

    private function seedLeads(User $owner): void
    {
        // open = 100k + 200k + 300k, won = 400k, lost = 500k  => total 1,500,000 over 5 leads
        Lead::factory()->ownedBy($owner)->stage(LeadStage::New)->create(['value' => 100000]);
        Lead::factory()->ownedBy($owner)->stage(LeadStage::Qualified)->create(['value' => 200000]);
        Lead::factory()->ownedBy($owner)->stage(LeadStage::Proposal)->create(['value' => 300000]);
        Lead::factory()->ownedBy($owner)->stage(LeadStage::Won)->create(['value' => 400000]);
        Lead::factory()->ownedBy($owner)->stage(LeadStage::Lost)->create(['value' => 500000]);
    }

    public function test_users_without_revenue_permission_receive_no_money_aggregates(): void
    {
        $rep = $this->userWithRole('Sales Rep');
        $viewer = $this->userWithRole('Viewer');
        $this->seedLeads($rep);

        foreach ([$rep, $viewer] as $user) {
            $stats = $this->stats($user);

            $this->assertSame(['total'], array_keys($stats));
            $this->assertSame(5, $stats['total']);

            $json = json_encode($this->actingAs($user)->get('/leads')->viewData('page')['props']['stats']);
            foreach (['600000', '400000', '300000', '1500000'] as $sum) {
                $this->assertStringNotContainsString($sum, $json);
            }
        }
    }

    public function test_users_with_revenue_permission_receive_the_aggregates(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $admin = $this->userWithRole('Admin');
        $this->seedLeads($manager);

        foreach ([$manager, $admin] as $user) {
            $stats = $this->stats($user);

            $this->assertSame(5, $stats['total']);
            $this->assertEquals(600000.0, $stats['open_pipeline']);
            $this->assertEquals(400000.0, $stats['won_value']);
            // Average of ALL in-scope leads: 1,500,000 / 5.
            $this->assertEquals(300000.0, $stats['avg_deal_size']);
        }
    }

    public function test_money_aggregates_are_computed_over_the_users_scope_only(): void
    {
        $rep = $this->userWithRole('Sales Rep');
        $rep->givePermissionTo('dashboard.view-revenue');

        Lead::factory()->ownedBy($rep)->stage(LeadStage::New)->create(['value' => 100000]);
        Lead::factory()->ownedBy($rep)->stage(LeadStage::Won)->create(['value' => 300000]);
        Lead::factory()->ownedBy($this->userWithRole('Sales Rep'))->stage(LeadStage::Won)->create(['value' => 9000000]);

        $stats = $this->stats($rep->fresh());

        $this->assertSame(2, $stats['total']);
        $this->assertEquals(100000.0, $stats['open_pipeline']);
        $this->assertEquals(300000.0, $stats['won_value']);
        $this->assertEquals(200000.0, $stats['avg_deal_size']);
    }

    public function test_the_average_is_zero_when_there_are_no_leads(): void
    {
        $stats = $this->stats($this->userWithRole('Sales Manager'));

        $this->assertSame(0, $stats['total']);
        $this->assertEquals(0, $stats['open_pipeline']);
        $this->assertEquals(0, $stats['won_value']);
        $this->assertEquals(0, $stats['avg_deal_size']);
    }

    public function test_a_lead_value_is_visible_to_anyone_who_can_view_leads(): void
    {
        $rep = $this->userWithRole('Sales Rep');
        $viewer = $this->userWithRole('Viewer');
        Lead::factory()->ownedBy($rep)->create(['value' => 85000]);

        foreach ([$rep, $viewer] as $user) {
            $rows = $this->actingAs($user)->get('/leads')->viewData('page')['props']['leads']['data'];

            $this->assertEquals(85000.0, $rows[0]['value']);
        }
    }

    public function test_money_is_sent_as_plain_numbers(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $this->seedLeads($manager);

        $json = json_encode($this->actingAs($manager)->get('/leads')->viewData('page')['props']);

        $this->assertStringNotContainsString('$', $json);
        $this->assertStringNotContainsString('Php', $json);
    }

    public function test_money_keys_never_appear_for_a_user_without_the_permission(): void
    {
        $viewer = $this->userWithRole('Viewer');

        $stats = $this->stats($viewer);

        foreach (self::MONEY_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $stats);
        }
    }
}
