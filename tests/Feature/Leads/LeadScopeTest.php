<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * The own/all scope: a Rep sees and touches only their own leads;
 * Admin, Manager and Viewer (leads.view-all) see everything.
 */
class LeadScopeTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private User $rep;

    private User $otherRep;

    private User $manager;

    private User $admin;

    private User $viewer;

    /** @var array<string, Lead> */
    private array $leads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->rep = $this->userWithRole('Sales Rep', ['name' => 'Rep One']);
        $this->otherRep = $this->userWithRole('Sales Rep', ['name' => 'Rep Two']);
        $this->manager = $this->userWithRole('Sales Manager');
        $this->admin = $this->userWithRole('Admin');
        $this->viewer = $this->userWithRole('Viewer');

        $this->leads = [
            'rep-new' => Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create(['name' => 'Rep Lead New', 'value' => 100000]),
            'rep-won' => Lead::factory()->ownedBy($this->rep)->stage(LeadStage::Won)->create(['name' => 'Rep Lead Won', 'value' => 200000]),
            'rep-lost' => Lead::factory()->ownedBy($this->rep)->stage(LeadStage::Lost)->create(['name' => 'Rep Lead Lost', 'value' => 300000]),
            'other-1' => Lead::factory()->ownedBy($this->otherRep)->stage(LeadStage::New)->create(['name' => 'Other Lead One', 'value' => 400000]),
            'other-2' => Lead::factory()->ownedBy($this->otherRep)->stage(LeadStage::Proposal)->create(['name' => 'Other Lead Two', 'value' => 500000]),
            'manager' => Lead::factory()->ownedBy($this->manager)->stage(LeadStage::Qualified)->create(['name' => 'Manager Lead', 'value' => 600000]),
        ];
    }

    private function props(User $user, string $url = '/leads'): array
    {
        return $this->actingAs($user)->get($url)->assertOk()->viewData('page')['props'];
    }

    private function ids(array $props): array
    {
        $ids = array_column($props['leads']['data'], 'id');
        sort($ids);

        return $ids;
    }

    private function idsOf(string ...$keys): array
    {
        $ids = array_map(fn (string $key) => $this->leads[$key]->id, $keys);
        sort($ids);

        return $ids;
    }

    public function test_a_rep_sees_only_their_own_leads_in_the_list_chips_and_stats(): void
    {
        $props = $this->props($this->rep);

        $this->assertSame($this->idsOf('rep-new', 'rep-won', 'rep-lost'), $this->ids($props));
        $this->assertSame(3, $props['leads']['total']);
        $this->assertSame(['all' => 3, 'new' => 1, 'qualified' => 0, 'proposal' => 0, 'won' => 1, 'lost' => 1], $props['stageCounts']);
        $this->assertSame(3, $props['stats']['total']);
    }

    public function test_manager_admin_and_viewer_see_every_lead(): void
    {
        foreach ([$this->manager, $this->admin, $this->viewer] as $user) {
            $props = $this->props($user);

            $this->assertCount(6, $props['leads']['data']);
            $this->assertSame(6, $props['stageCounts']['all']);
            $this->assertSame(6, $props['stats']['total']);
        }
    }

    public function test_search_and_filters_never_widen_a_reps_scope(): void
    {
        $props = $this->props($this->rep, '/leads?search=Lead');
        $this->assertSame($this->idsOf('rep-new', 'rep-won', 'rep-lost'), $this->ids($props));

        $props = $this->props($this->rep, '/leads?search=Other');
        $this->assertSame([], $this->ids($props));
        $this->assertSame(0, $props['stageCounts']['all']);

        // The stat cards stay on the whole scope, whatever the filters say.
        $this->assertSame(3, $props['stats']['total']);
    }

    public function test_a_rep_gets_404_for_another_users_lead_on_every_action(): void
    {
        $other = $this->leads['other-1'];
        $this->actingAs($this->rep);

        $this->get("/leads/{$other->id}")->assertNotFound();
        $this->get("/leads/{$other->id}/edit")->assertNotFound();
        $this->put("/leads/{$other->id}", $this->validPayload())->assertNotFound();
        $this->delete("/leads/{$other->id}")->assertNotFound();

        $this->assertNotNull($other->fresh());
    }

    public function test_an_out_of_scope_lead_answers_404_even_for_invalid_input(): void
    {
        $other = $this->leads['other-1'];

        // A 422 here would reveal that the lead exists.
        $this->actingAs($this->rep)
            ->put("/leads/{$other->id}", ['name' => '', 'stage' => 'nonsense'])
            ->assertNotFound();
    }

    public function test_a_missing_or_deleted_lead_is_a_404_for_everyone(): void
    {
        $deleted = Lead::factory()->ownedBy($this->rep)->create();
        $deleted->delete();

        foreach ([$this->rep, $this->admin] as $user) {
            $this->actingAs($user)->get('/leads/999999')->assertNotFound();
            $this->actingAs($user)->get("/leads/{$deleted->id}")->assertNotFound();
        }
    }

    public function test_a_rep_can_open_and_edit_their_own_lead(): void
    {
        $own = $this->leads['rep-new'];

        $this->actingAs($this->rep)->get("/leads/{$own->id}")->assertOk();
        $this->actingAs($this->rep)->get("/leads/{$own->id}/edit")->assertOk();
        $this->actingAs($this->rep)->put("/leads/{$own->id}", $this->validPayload(['name' => 'Renamed']))->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $own->fresh()->name);
    }

    public function test_managers_and_admins_can_act_on_any_lead(): void
    {
        $other = $this->leads['other-1'];

        foreach ([$this->manager, $this->admin] as $user) {
            $this->actingAs($user)->get("/leads/{$other->id}")->assertOk();
            $this->actingAs($user)->get("/leads/{$other->id}/edit")->assertOk();
            $this->actingAs($user)->put("/leads/{$other->id}", $this->validPayload(['name' => "Edited by {$user->id}"]))->assertSessionHasNoErrors();
            $this->assertSame("Edited by {$user->id}", $other->fresh()->name);
        }

        $this->actingAs($this->manager)->delete("/leads/{$other->id}")->assertRedirect();
        $this->assertSoftDeleted($other);
    }

    public function test_a_rep_without_the_delete_permission_gets_403_on_their_own_lead(): void
    {
        $own = $this->leads['rep-new'];

        $this->actingAs($this->rep)->delete("/leads/{$own->id}")->assertForbidden();

        $this->assertNotSoftDeleted($own);
    }

    public function test_a_rep_who_is_granted_leads_delete_can_delete_only_their_own(): void
    {
        Role::findByName('Sales Rep')->givePermissionTo('leads.delete');

        $this->actingAs($this->rep)->delete("/leads/{$this->leads['rep-new']->id}")->assertRedirect();
        $this->actingAs($this->rep)->delete("/leads/{$this->leads['other-1']->id}")->assertNotFound();

        $this->assertSoftDeleted($this->leads['rep-new']);
        $this->assertNotSoftDeleted($this->leads['other-1']);
    }

    public function test_bulk_delete_removes_only_the_leads_in_scope(): void
    {
        Role::findByName('Sales Rep')->givePermissionTo('leads.delete');

        $response = $this->actingAs($this->rep)->delete('/leads', [
            'ids' => [
                $this->leads['rep-new']->id,
                $this->leads['rep-won']->id,
                $this->leads['other-1']->id,   // someone else's
                $this->leads['manager']->id,   // someone else's
                999999,                        // does not exist
            ],
        ]);

        $response->assertRedirect('/leads')->assertSessionHas('success', '2 leads were deleted.');

        $this->assertSoftDeleted($this->leads['rep-new']);
        $this->assertSoftDeleted($this->leads['rep-won']);
        $this->assertNotSoftDeleted($this->leads['rep-lost']);
        $this->assertNotSoftDeleted($this->leads['other-1']);
        $this->assertNotSoftDeleted($this->leads['manager']);
    }

    public function test_a_manager_bulk_delete_reaches_every_lead(): void
    {
        $this->actingAs($this->manager)->delete('/leads', [
            'ids' => [$this->leads['rep-new']->id, $this->leads['other-1']->id],
        ])->assertSessionHas('success', '2 leads were deleted.');

        $this->assertSoftDeleted($this->leads['rep-new']);
        $this->assertSoftDeleted($this->leads['other-1']);
    }

    public function test_bulk_delete_is_validated_and_limited(): void
    {
        $this->actingAs($this->manager)->delete('/leads', [])->assertSessionHasErrors('ids');
        $this->actingAs($this->manager)->delete('/leads', ['ids' => ['abc']])->assertSessionHasErrors('ids.0');
        $this->actingAs($this->manager)->delete('/leads', ['ids' => range(1, 101)])->assertSessionHasErrors('ids');
    }

    public function test_the_csv_export_contains_only_the_leads_in_scope(): void
    {
        $csv = $this->actingAs($this->manager)->get('/leads/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Other Lead One', $csv);
        $this->assertStringContainsString('Manager Lead', $csv);

        // A rep has no leads.export, so even the scope question never comes up.
        $this->actingAs($this->rep)->get('/leads/export')->assertForbidden();

        // Granted export, the rep's CSV still holds only their own leads.
        Role::findByName('Sales Rep')->givePermissionTo('leads.export');
        $csv = $this->actingAs($this->rep->fresh())->get('/leads/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('Rep Lead New', $csv);
        $this->assertStringNotContainsString('Other Lead', $csv);
        $this->assertStringNotContainsString('Manager Lead', $csv);
    }

    public function test_per_row_abilities_follow_the_policy(): void
    {
        Role::findByName('Sales Rep')->givePermissionTo('leads.delete');

        foreach ($this->props($this->rep)['leads']['data'] as $row) {
            $this->assertSame(['update' => true, 'delete' => true], $row['can']);
        }

        // Viewer sees everything but may change nothing.
        foreach ($this->props($this->viewer)['leads']['data'] as $row) {
            $this->assertSame(['update' => false, 'delete' => false], $row['can']);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Valid Name',
            'company' => 'Valid Company',
            'email' => 'valid@example.com',
            'phone' => '0917 123 4567',
            'stage' => 'qualified',
            'priority' => 'high',
            'source' => 'referral',
            'value' => '125000',
            'notes' => 'Some notes',
            ...$overrides,
        ];
    }
}
