<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class LeadCrudTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Maria Santos',
            'company' => 'Santos Trading',
            'email' => 'maria@example.ph',
            'phone' => '0917 123 4567',
            'stage' => 'new',
            'priority' => 'medium',
            'source' => 'website',
            'value' => '85000',
            'notes' => 'Met at the trade fair.',
            ...$overrides,
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/leads', '/leads/create', '/leads/1', '/leads/export'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->post('/leads', $this->payload())->assertRedirect('/login');
    }

    public function test_a_user_without_leads_view_gets_403_everywhere(): void
    {
        $lead = Lead::factory()->create();
        $user = User::factory()->create(); // no role at all

        $this->actingAs($user);

        $this->get('/leads')->assertForbidden();
        $this->get('/leads/create')->assertForbidden();
        $this->get("/leads/{$lead->id}")->assertForbidden();
        $this->get('/leads/999999')->assertForbidden(); // same answer: no existence leak
        $this->post('/leads', $this->payload())->assertForbidden();
        $this->delete('/leads', ['ids' => [$lead->id]])->assertForbidden();
        $this->get('/leads/export')->assertForbidden();
    }

    public function test_owner_is_set_to_the_creator(): void
    {
        $rep = $this->userWithRole('Sales Rep');

        $this->actingAs($rep)->post('/leads', $this->payload())->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertSame($rep->id, $lead->owner_id);
        $this->assertSame('Maria Santos', $lead->name);
        $this->assertSame('85000.00', $lead->value);
    }

    public function test_a_rep_sending_owner_id_is_ignored_on_create_and_update(): void
    {
        $rep = $this->userWithRole('Sales Rep');
        $other = $this->userWithRole('Sales Rep');

        $this->actingAs($rep)->post('/leads', $this->payload(['owner_id' => $other->id]))->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertSame($rep->id, $lead->owner_id, 'owner_id from a rep must be ignored on create');

        $this->actingAs($rep)->put("/leads/{$lead->id}", $this->payload(['owner_id' => $other->id]))->assertSessionHasNoErrors();
        $this->assertSame($rep->id, $lead->fresh()->owner_id, 'owner_id from a rep must be ignored on update');

        // Not even an invalid owner_id produces an error: the field simply does not exist for a rep.
        $this->actingAs($rep)->put("/leads/{$lead->id}", $this->payload(['owner_id' => 'garbage']))->assertSessionHasNoErrors();
    }

    public function test_a_manager_can_assign_and_reassign_the_owner(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $rep = $this->userWithRole('Sales Rep');
        $otherRep = $this->userWithRole('Sales Rep');

        $this->actingAs($manager)->post('/leads', $this->payload(['owner_id' => $rep->id]))->assertSessionHasNoErrors();
        $lead = Lead::firstOrFail();
        $this->assertSame($rep->id, $lead->owner_id);

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['owner_id' => $otherRep->id]))->assertSessionHasNoErrors();
        $this->assertSame($otherRep->id, $lead->fresh()->owner_id);

        // Without owner_id the manager owns a new lead; an update keeps the current owner.
        $this->actingAs($manager)->post('/leads', $this->payload(['name' => 'Second']))->assertSessionHasNoErrors();
        $this->assertSame($manager->id, Lead::where('name', 'Second')->firstOrFail()->owner_id);

        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame($otherRep->id, $lead->fresh()->owner_id);
    }

    public function test_the_owner_must_be_an_existing_active_user(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $inactive = $this->userWithRole('Sales Rep', ['is_active' => false]);

        $this->actingAs($manager)->post('/leads', $this->payload(['owner_id' => $inactive->id]))->assertSessionHasErrors('owner_id');
        $this->actingAs($manager)->post('/leads', $this->payload(['owner_id' => 999999]))->assertSessionHasErrors('owner_id');

        // A lead already owned by a user who was deactivated since can still be edited.
        $lead = Lead::factory()->ownedBy($inactive)->create();
        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['owner_id' => $inactive->id]))->assertSessionHasNoErrors();
    }

    public function test_the_assign_to_list_is_only_given_to_users_who_may_assign(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $this->userWithRole('Sales Rep', ['name' => 'Inactive Person', 'is_active' => false]);
        $rep = $this->userWithRole('Sales Rep');
        $viewer = $this->userWithRole('Viewer');

        $owners = $this->actingAs($manager)->get('/leads/create')->viewData('page')['props']['owners'];
        $this->assertContains($rep->id, array_column($owners, 'id'));
        $this->assertNotContains('Inactive Person', array_column($owners, 'name'));
        $this->assertSame(['id', 'name'], array_keys($owners[0]));

        $this->assertSame([], $this->actingAs($rep)->get('/leads/create')->viewData('page')['props']['owners']);
        // The viewer has view-all but cannot create or edit, so gets no list of users either.
        $this->assertSame([], $this->actingAs($viewer)->get('/leads')->viewData('page')['props']['owners']);
    }

    public function test_a_viewer_cannot_create_update_delete_or_export(): void
    {
        $viewer = $this->userWithRole('Viewer');
        $lead = Lead::factory()->create();

        $this->actingAs($viewer);

        $this->get('/leads')->assertOk();
        $this->get("/leads/{$lead->id}")->assertOk();

        $this->get('/leads/create')->assertForbidden();
        $this->post('/leads', $this->payload())->assertForbidden();
        $this->get("/leads/{$lead->id}/edit")->assertForbidden();
        $this->put("/leads/{$lead->id}", $this->payload())->assertForbidden();
        $this->delete("/leads/{$lead->id}")->assertForbidden();
        $this->delete('/leads', ['ids' => [$lead->id]])->assertForbidden();
        $this->get('/leads/export')->assertForbidden();

        $this->assertSame(1, Lead::count());
        $this->assertNotSame('Maria Santos', $lead->fresh()->name);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'missing company' => [['company' => ''], 'company'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'bad phone' => [['phone' => 'call me maybe'], 'phone'],
            'unknown stage' => [['stage' => 'negotiating'], 'stage'],
            'unknown priority' => [['priority' => 'urgent'], 'priority'],
            'unknown source' => [['source' => 'billboard'], 'source'],
            'missing stage' => [['stage' => null], 'stage'],
            'negative value' => [['value' => '-5'], 'value'],
            'text value' => [['value' => 'a lot'], 'value'],
            'huge value' => [['value' => '99999999999'], 'value'],
            'long notes' => [['notes' => str_repeat('x', 5001)], 'notes'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(array $override, string $field): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $this->actingAs($manager)->post('/leads', $this->payload($override))->assertSessionHasErrors($field);
        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload($override))->assertSessionHasErrors($field);

        $this->assertSame(1, Lead::count());
    }

    public function test_optional_fields_can_be_empty_and_value_defaults_to_zero(): void
    {
        $manager = $this->userWithRole('Sales Manager');

        $this->actingAs($manager)->post('/leads', $this->payload([
            'email' => '', 'phone' => '', 'notes' => '', 'value' => '',
        ]))->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertNull($lead->email);
        $this->assertNull($lead->phone);
        $this->assertNull($lead->notes);
        $this->assertSame('0.00', $lead->value);
    }

    public function test_creating_and_editing_land_on_the_lead_with_a_message(): void
    {
        $manager = $this->userWithRole('Sales Manager');

        $this->actingAs($manager)->post('/leads', $this->payload())
            ->assertRedirect('/leads/'.(Lead::firstOrFail()->id))
            ->assertSessionHas('success');

        $lead = Lead::firstOrFail();
        $this->actingAs($manager)->put("/leads/{$lead->id}", $this->payload(['name' => 'Changed']))
            ->assertRedirect("/leads/{$lead->id}")
            ->assertSessionHas('success');
    }

    public function test_the_page_state_is_kept_in_redirects(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create();

        $this->actingAs($manager)->delete("/leads/{$lead->id}?search=abc&stage=won&sort=value&direction=asc")
            ->assertRedirect('/leads?search=abc&stage=won&sort=value&direction=asc');
    }

    public function test_the_drawer_is_rendered_for_show_edit_and_create(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $lead = Lead::factory()->ownedBy($manager)->create(['name' => 'Drawer Lead', 'notes' => 'Private notes']);

        $this->actingAs($manager);

        $show = $this->get("/leads/{$lead->id}")->viewData('page')['props'];
        $this->assertSame('show', $show['drawer']['mode']);
        $this->assertSame('Drawer Lead', $show['drawer']['lead']['name']);
        $this->assertSame('Private notes', $show['drawer']['lead']['notes']);

        $this->assertSame('edit', $this->get("/leads/{$lead->id}/edit")->viewData('page')['props']['drawer']['mode']);
        $this->assertSame(['mode' => 'create', 'lead' => null], $this->get('/leads/create')->viewData('page')['props']['drawer']);
        $this->assertNull($this->get('/leads')->viewData('page')['props']['drawer']);
    }

    public function test_no_secrets_or_unrelated_user_data_leave_the_server(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $rep = $this->userWithRole('Sales Rep', ['email' => 'private-rep@example.com']);
        $lead = Lead::factory()->ownedBy($rep)->create();

        $json = json_encode($this->actingAs($manager)->get("/leads/{$lead->id}")->viewData('page')['props']);

        $this->assertStringNotContainsString($rep->password, $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('private-rep@example.com', $json);
        $this->assertStringContainsString($rep->name, $json); // only the owner's name is shown
    }
}
