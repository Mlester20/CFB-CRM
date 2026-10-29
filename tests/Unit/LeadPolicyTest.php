<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Every role against every ability, for a lead the user owns and one they do not.
 * Expected answer: 'allow', 403, or 404 (a lead the user may not even see).
 */
class LeadPolicyTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    /**
     * role => ability => [own lead, someone else's lead]
     *
     * Rep has no leads.delete in the approved matrix, so a rep's delete is 403 on their own lead.
     */
    public static function matrix(): array
    {
        $allow = ['allow', 'allow'];
        $deny = [403, 403];

        return [
            'Admin' => ['Admin', [
                'viewAny' => $allow, 'create' => $allow, 'export' => $allow, 'deleteAny' => $allow,
                'view' => $allow, 'update' => $allow, 'delete' => $allow,
            ]],
            'Sales Manager' => ['Sales Manager', [
                'viewAny' => $allow, 'create' => $allow, 'export' => $allow, 'deleteAny' => $allow,
                'view' => $allow, 'update' => $allow, 'delete' => $allow,
            ]],
            'Sales Rep' => ['Sales Rep', [
                'viewAny' => $allow, 'create' => $allow, 'export' => $deny, 'deleteAny' => $deny,
                'view' => ['allow', 404], 'update' => ['allow', 404], 'delete' => [403, 404],
            ]],
            'Viewer' => ['Viewer', [
                'viewAny' => $allow, 'create' => $deny, 'export' => $deny, 'deleteAny' => $deny,
                'view' => $allow, 'update' => $deny, 'delete' => $deny,
            ]],
        ];
    }

    private function answer(User $user, string $ability, Lead|string $subject): string|int
    {
        $response = Gate::forUser($user)->inspect($ability, $subject);

        return $response->allowed() ? 'allow' : ($response->status() ?? 403);
    }

    #[DataProvider('matrix')]
    public function test_each_role_gets_the_expected_answer_for_every_ability(string $role, array $expected): void
    {
        $this->seedRbac();

        $user = $this->userWithRole($role);
        $own = Lead::factory()->ownedBy($user)->create();
        $others = Lead::factory()->create();

        foreach (['viewAny', 'create', 'export', 'deleteAny'] as $ability) {
            $this->assertSame($expected[$ability][0], $this->answer($user, $ability, Lead::class), "{$role} {$ability}");
        }

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertSame($expected[$ability][0], $this->answer($user, $ability, $own), "{$role} {$ability} own lead");
            $this->assertSame($expected[$ability][1], $this->answer($user, $ability, $others), "{$role} {$ability} someone else's lead");
        }
    }

    public function test_a_rep_granted_delete_can_delete_only_their_own_lead(): void
    {
        $this->seedRbac();
        \Spatie\Permission\Models\Role::findByName('Sales Rep')->givePermissionTo('leads.delete');

        $rep = $this->userWithRole('Sales Rep');

        $this->assertSame('allow', $this->answer($rep, 'delete', Lead::factory()->ownedBy($rep)->create()));
        $this->assertSame(404, $this->answer($rep, 'delete', Lead::factory()->create()));
        $this->assertSame('allow', $this->answer($rep, 'deleteAny', Lead::class));
    }

    public function test_the_scope_hides_leads_without_view_all_and_shows_all_with_it(): void
    {
        $this->seedRbac();

        $rep = $this->userWithRole('Sales Rep');
        $manager = $this->userWithRole('Sales Manager');
        $mine = Lead::factory()->ownedBy($rep)->create();
        Lead::factory()->count(2)->create();

        $this->assertSame([$mine->id], Lead::visibleTo($rep)->pluck('id')->all());
        $this->assertSame(3, Lead::visibleTo($manager)->count());
    }

    public function test_a_user_with_no_permissions_at_all_sees_only_their_own_and_may_do_nothing(): void
    {
        $this->seedRbac();

        $user = User::factory()->create();
        $own = Lead::factory()->ownedBy($user)->create();

        $this->assertSame([$own->id], Lead::visibleTo($user)->pluck('id')->all());
        $this->assertSame(403, $this->answer($user, 'viewAny', Lead::class));
        $this->assertSame(403, $this->answer($user, 'update', $own));
    }
}
