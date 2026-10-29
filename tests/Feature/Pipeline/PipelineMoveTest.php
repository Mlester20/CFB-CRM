<?php

namespace Tests\Feature\Pipeline;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PipelineMoveTest extends TestCase
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

    /** Creates leads so that they end up in the given order (top first). */
    private function column(User $owner, LeadStage $stage, int $count): array
    {
        $leads = [];
        for ($i = 0; $i < $count; $i++) {
            $leads[] = Lead::factory()->ownedBy($owner)->stage($stage)->create();
        }

        // each new lead goes on top, so the last created is first
        return array_reverse($leads);
    }

    private function order(LeadStage $stage): array
    {
        return Lead::where('stage', $stage->value)->orderBy('pipeline_position')->pluck('id')->all();
    }

    private function positions(LeadStage $stage): array
    {
        return Lead::where('stage', $stage->value)->orderBy('pipeline_position')->pluck('pipeline_position')->all();
    }

    private function assertDense(LeadStage $stage): void
    {
        $count = Lead::where('stage', $stage->value)->count();
        $this->assertSame($count ? range(0, $count - 1) : [], $this->positions($stage), "positions of {$stage->value} are not 0..n-1");
    }

    private function move(User $user, Lead|int $lead, string $stage, int $position, array $extra = [])
    {
        $id = $lead instanceof Lead ? $lead->id : $lead;

        return $this->actingAs($user)->patchJson("/pipeline/{$id}/move", ['stage' => $stage, 'position' => $position, ...$extra]);
    }

    // ============================================================ permissions

    public function test_a_guest_cannot_move(): void
    {
        $lead = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();

        $this->patchJson("/pipeline/{$lead->id}/move", ['stage' => 'won', 'position' => 0])->assertUnauthorized();
    }

    public function test_a_viewer_can_see_the_board_but_cannot_move(): void
    {
        $lead = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();

        $this->move($this->userWithRole('Viewer'), $lead, 'won', 0)->assertForbidden();
        $this->assertSame(LeadStage::New, $lead->fresh()->stage);
    }

    public function test_a_user_without_deals_view_cannot_move(): void
    {
        $lead = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();
        $user = User::factory()->create();
        $user->givePermissionTo('deals.move');

        $this->move($user, $lead, 'won', 0)->assertForbidden();
    }

    public function test_a_rep_moves_their_own_lead_but_not_someone_elses(): void
    {
        $mine = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();
        $theirs = Lead::factory()->ownedBy($this->otherRep)->stage(LeadStage::New)->create();

        $this->move($this->rep, $mine, 'qualified', 0)->assertOk();
        $this->assertSame(LeadStage::Qualified, $mine->fresh()->stage);

        $this->move($this->rep, $theirs, 'qualified', 0)->assertNotFound();
        $this->assertSame(LeadStage::New, $theirs->fresh()->stage);
        $this->move($this->rep, 999999, 'qualified', 0)->assertNotFound();
    }

    public function test_managers_and_admins_move_any_lead(): void
    {
        foreach (['Sales Manager', 'Admin'] as $role) {
            $lead = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();

            $this->move($this->userWithRole($role), $lead, 'proposal', 0)->assertOk();
            $this->assertSame(LeadStage::Proposal, $lead->fresh()->stage, $role);
        }
    }

    public function test_moving_into_won_or_lost_has_no_extra_rule(): void
    {
        $lead = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();

        $this->move($this->rep, $lead, 'won', 0)->assertOk();
        $this->move($this->rep, $lead, 'lost', 0)->assertOk();
        $this->move($this->rep, $lead, 'new', 0)->assertOk();
    }

    public function test_the_stage_and_position_are_validated(): void
    {
        $lead = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();

        $this->move($this->manager, $lead, 'bogus', 0)->assertStatus(422)->assertJsonValidationErrors('stage');
        $this->move($this->manager, $lead, 'won', -1)->assertStatus(422)->assertJsonValidationErrors('position');
        $this->actingAs($this->manager)->patchJson("/pipeline/{$lead->id}/move", ['stage' => 'won'])->assertStatus(422);
        $this->actingAs($this->manager)->patchJson("/pipeline/{$lead->id}/move", ['stage' => 'won', 'position' => 'x'])->assertStatus(422);

        $this->assertSame(LeadStage::New, $lead->fresh()->stage);
    }

    // ============================================================ ordering

    public function test_moving_to_another_column_keeps_both_columns_contiguous(): void
    {
        [$a, $b, $c] = $this->column($this->manager, LeadStage::New, 3);
        [$x, $y] = $this->column($this->manager, LeadStage::Qualified, 2);

        $json = $this->move($this->manager, $b, 'qualified', 1)->assertOk()->json();

        $this->assertSame([$a->id, $c->id], $this->order(LeadStage::New));
        $this->assertSame([$x->id, $b->id, $y->id], $this->order(LeadStage::Qualified));
        $this->assertDense(LeadStage::New);
        $this->assertDense(LeadStage::Qualified);
        $this->assertSame(['id' => $b->id, 'stage' => 'qualified', 'position' => 1], $json['lead']);
    }

    public function test_dropping_on_top_in_the_middle_at_the_end_and_far_beyond(): void
    {
        [$a, $b, $c] = $this->column($this->manager, LeadStage::Qualified, 3);
        $m = fn () => Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();

        $this->move($this->manager, $m1 = $m(), 'qualified', 0);
        $this->assertSame([$m1->id, $a->id, $b->id, $c->id], $this->order(LeadStage::Qualified));

        $this->move($this->manager, $m2 = $m(), 'qualified', 2);
        $this->assertSame([$m1->id, $a->id, $m2->id, $b->id, $c->id], $this->order(LeadStage::Qualified));

        $this->move($this->manager, $m3 = $m(), 'qualified', 5);
        $this->assertSame([$m1->id, $a->id, $m2->id, $b->id, $c->id, $m3->id], $this->order(LeadStage::Qualified));

        $this->move($this->manager, $m4 = $m(), 'qualified', 9999);
        $this->assertSame($m4->id, last($this->order(LeadStage::Qualified)), 'a position past the end means the end');

        $this->assertDense(LeadStage::Qualified);
        $this->assertDense(LeadStage::New);
    }

    public function test_moving_into_an_empty_column(): void
    {
        [$a] = $this->column($this->manager, LeadStage::New, 1);

        $this->move($this->manager, $a, 'lost', 4)->assertOk();

        $this->assertSame([0], $this->positions(LeadStage::Lost));
        $this->assertSame([], $this->order(LeadStage::New));
    }

    public function test_reordering_inside_a_column(): void
    {
        [$a, $b, $c, $d] = $this->column($this->manager, LeadStage::Proposal, 4);

        // move the top card to index 2 (index among the OTHER cards: b, c, d)
        $this->move($this->manager, $a, 'proposal', 2)->assertOk();
        $this->assertSame([$b->id, $c->id, $a->id, $d->id], $this->order(LeadStage::Proposal));

        // and the last one to the top
        $this->move($this->manager, $d, 'proposal', 0)->assertOk();
        $this->assertSame([$d->id, $b->id, $c->id, $a->id], $this->order(LeadStage::Proposal));

        // to the very end
        $this->move($this->manager, $d, 'proposal', 3)->assertOk();
        $this->assertSame([$b->id, $c->id, $a->id, $d->id], $this->order(LeadStage::Proposal));

        $this->assertDense(LeadStage::Proposal);
    }

    public function test_dropping_a_card_where_it_already_is_changes_nothing(): void
    {
        [$a, $b, $c] = $this->column($this->manager, LeadStage::New, 3);

        $this->move($this->manager, $b, 'new', 1)->assertOk();

        $this->assertSame([$a->id, $b->id, $c->id], $this->order(LeadStage::New));
        $this->assertDense(LeadStage::New);
    }

    public function test_a_rep_position_counts_only_the_cards_they_see(): void
    {
        // column (top to bottom): m1(manager) r1(rep) m2(manager) r2(rep) m3(manager)
        $col = fn (User $u) => Lead::factory()->ownedBy($u)->stage(LeadStage::Qualified)->create();
        [$m3, $r2, $m2, $r1, $m1] = [$col($this->manager), $col($this->rep), $col($this->manager), $col($this->rep), $col($this->manager)];
        // creation put the last on top: order is m1 r1 m2 r2 m3 reversed -> verify
        $this->assertSame([$m1->id, $r1->id, $m2->id, $r2->id, $m3->id], $this->order(LeadStage::Qualified));

        $mine = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();

        // the rep sees [r1, r2]; dropping at index 1 means "between r1 and r2" -> before r2
        $this->move($this->rep, $mine, 'qualified', 1)->assertOk();
        $this->assertSame([$m1->id, $r1->id, $m2->id, $mine->id, $r2->id, $m3->id], $this->order(LeadStage::Qualified));

        // index 0 = before their first card, index past the end = after their last
        $second = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();
        $this->move($this->rep, $second, 'qualified', 0)->assertOk();
        $this->assertSame($second->id, $this->order(LeadStage::Qualified)[1], 'before r1, which is at position 1');

        $third = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();
        $this->move($this->rep, $third, 'qualified', 50)->assertOk();
        $order = $this->order(LeadStage::Qualified);
        $this->assertSame($third->id, $order[array_search($r2->id, $order) + 1], 'right after their last visible card');

        $this->assertDense(LeadStage::Qualified);
        $this->assertSame(8, Lead::where('stage', 'qualified')->count());
    }

    public function test_a_rep_dropping_into_a_column_with_only_other_peoples_cards(): void
    {
        [$m1, $m2] = $this->column($this->manager, LeadStage::Proposal, 2);
        $mine = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();

        $this->move($this->rep, $mine, 'proposal', 3)->assertOk();

        $this->assertSame($mine->id, $this->order(LeadStage::Proposal)[0], 'nothing visible to them: it goes on top');
        $this->assertDense(LeadStage::Proposal);
    }

    // ====================================================== stage_changed_at

    public function test_stage_changed_at_is_set_only_when_the_stage_changes(): void
    {
        [$a, $b] = $this->column($this->manager, LeadStage::New, 2);
        Lead::query()->update(['stage_changed_at' => null]);

        $this->move($this->manager, $a, 'new', 1)->assertOk();
        $this->assertNull($a->fresh()->stage_changed_at, 'a reorder is not a stage change');
        $this->assertNull($b->fresh()->stage_changed_at, 'neither is the shifting of the neighbours');

        $this->travelTo(now()->addDay());
        $this->move($this->manager, $a, 'proposal', 0)->assertOk();

        $this->assertEquals(now()->toDateTimeString(), $a->fresh()->stage_changed_at->toDateTimeString());
        $this->assertNull($b->fresh()->stage_changed_at);
    }

    public function test_a_reorder_does_not_touch_updated_at(): void
    {
        [$a] = $this->column($this->manager, LeadStage::New, 2);
        $before = $a->fresh()->updated_at;

        $this->travelTo(now()->addDays(2));
        $this->move($this->manager, $a, 'new', 1)->assertOk();

        $this->assertEquals($before, $a->fresh()->updated_at);
    }

    public function test_new_leads_get_a_stage_changed_at_and_land_on_top(): void
    {
        [$a] = $this->column($this->manager, LeadStage::New, 1);

        $this->actingAs($this->manager)->post('/leads', [
            'name' => 'Fresh Lead', 'company' => 'Fresh Co', 'stage' => 'new', 'priority' => 'medium', 'source' => 'website', 'value' => 100,
        ]);

        $fresh = Lead::where('name', 'Fresh Lead')->firstOrFail();
        $this->assertNotNull($fresh->stage_changed_at);
        $this->assertSame([$fresh->id, $a->id], $this->order(LeadStage::New));
        $this->assertDense(LeadStage::New);
    }

    // ============================================== changes made outside the board

    public function test_changing_the_stage_in_the_leads_form_keeps_the_board_order(): void
    {
        [$a, $b, $c] = $this->column($this->manager, LeadStage::New, 3);
        [$x] = $this->column($this->manager, LeadStage::Won, 1);

        $b->update(['stage' => LeadStage::Won]);

        $this->assertSame([$a->id, $c->id], $this->order(LeadStage::New));
        $this->assertSame([$b->id, $x->id], $this->order(LeadStage::Won));
        $this->assertNotNull($b->fresh()->stage_changed_at);
        $this->assertDense(LeadStage::New);
        $this->assertDense(LeadStage::Won);
    }

    public function test_editing_other_fields_does_not_move_the_card(): void
    {
        [$a, $b] = $this->column($this->manager, LeadStage::New, 2);

        $b->update(['name' => 'Renamed']);

        $this->assertSame([$a->id, $b->id], $this->order(LeadStage::New));
    }

    public function test_deleting_a_lead_closes_the_gap(): void
    {
        [$a, $b, $c] = $this->column($this->manager, LeadStage::New, 3);

        $b->delete();

        $this->assertSame([$a->id, $c->id], $this->order(LeadStage::New));
        $this->assertDense(LeadStage::New);
    }

    // ============================================================ response

    public function test_the_response_has_fresh_totals_for_both_columns_and_the_stats(): void
    {
        [$a] = $this->column($this->manager, LeadStage::New, 2);
        $a->update(['value' => 700]);
        $before = Lead::where('stage', 'new')->sum('value');

        $json = $this->move($this->manager, $a, 'won', 0)->assertOk()->json();

        $this->assertEqualsCanonicalizing(['new', 'won'], array_keys($json['columns']));
        $this->assertSame(1, $json['columns']['new']['count']);
        $this->assertSame(1, $json['columns']['won']['count']);
        $this->assertEquals(700, $json['columns']['won']['value']);
        $this->assertEquals($before - 700, $json['columns']['new']['value']);
        $this->assertSame(1, $json['stats']['open_deals']);
        $this->assertEquals(700, $json['stats']['won_value']);
    }

    public function test_a_reorder_response_only_lists_the_one_column(): void
    {
        [$a] = $this->column($this->manager, LeadStage::New, 2);

        $json = $this->move($this->manager, $a, 'new', 1)->assertOk()->json();

        $this->assertSame(['new'], array_keys($json['columns']));
    }

    public function test_the_filters_in_a_move_request_shape_the_returned_counts(): void
    {
        $quiet = ['company' => 'Zzz', 'email' => null];
        $keep = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create([...$quiet, 'name' => 'Anna']);
        Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create([...$quiet, 'name' => 'Bob']);

        $json = $this->move($this->manager, $keep, 'qualified', 0, ['search' => 'bob'])->assertOk()->json();

        $this->assertSame(1, $json['columns']['new']['count'], 'the filtered view only has Bob left');
        $this->assertSame(0, $json['columns']['qualified']['count'], 'Anna does not match the filter');
    }

    // ============================================================ concurrency

    public function test_moves_in_a_row_in_every_direction_never_corrupt_the_order(): void
    {
        $stages = LeadStage::cases();
        foreach ($stages as $stage) {
            $this->column($this->manager, $stage, 4);
        }
        $this->column($this->rep, LeadStage::New, 3);
        $ids = Lead::pluck('id')->all();

        mt_srand(42);
        for ($i = 0; $i < 120; $i++) {
            $user = mt_rand(0, 3) === 0 ? $this->rep : $this->manager;
            $lead = Lead::visibleTo($user, 'deals.view-all')->inRandomOrder()->first();
            $stage = $stages[mt_rand(0, 4)];

            $this->move($user, $lead, $stage->value, mt_rand(0, 6))->assertOk();
        }

        foreach ($stages as $stage) {
            $this->assertDense($stage);
        }
        $this->assertEqualsCanonicalizing($ids, Lead::pluck('id')->all(), 'no lead lost or duplicated');
    }

    public function test_a_stale_client_view_cannot_corrupt_the_order(): void
    {
        // Two users drag from the same stale picture of the board, one after the other.
        [$a, $b, $c] = $this->column($this->manager, LeadStage::New, 3);
        $other = $this->userWithRole('Sales Manager');

        // both think the column is [a, b, c] and both put "a" / "c" at index 1
        $this->move($this->manager, $a, 'qualified', 0)->assertOk();
        $this->move($other, $c, 'qualified', 0)->assertOk();
        $this->move($this->manager, $b, 'qualified', 1)->assertOk();

        $this->assertDense(LeadStage::Qualified);
        $this->assertDense(LeadStage::New);
        $this->assertSame([$c->id, $b->id, $a->id], $this->order(LeadStage::Qualified));
    }

    public function test_a_lead_reassigned_while_waiting_is_not_moved_by_its_old_owner(): void
    {
        $lead = Lead::factory()->ownedBy($this->rep)->stage(LeadStage::New)->create();

        // The rep's request was authorised, then a manager hands the lead to someone else
        // before the transaction takes its lock: the mover re-checks under the lock.
        $stale = Lead::find($lead->id);
        $lead->owner_id = $this->otherRep->id;
        $lead->save();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        app(\App\Services\Pipeline\PipelineMover::class)->move($stale, $this->rep, LeadStage::Won, 0);
    }

    public function test_the_move_endpoint_is_throttled(): void
    {
        $lead = Lead::factory()->ownedBy($this->manager)->stage(LeadStage::New)->create();

        $last = null;
        for ($i = 0; $i < 125; $i++) {
            $last = $this->move($this->manager, $lead, $i % 2 ? 'new' : 'qualified', 0)->status();
        }

        $this->assertSame(429, $last);
    }
}
