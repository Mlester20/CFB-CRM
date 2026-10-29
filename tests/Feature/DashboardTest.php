<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private const REVENUE = ['pipeline', 'weeklyRevenue', 'revenueGoal'];

    private const LEADS = ['conversion', 'engagement', 'activity', 'sources'];

    private const WIDGETS = [
        'pipeline', 'weeklyRevenue', 'revenueGoal',
        'conversion', 'engagement', 'activity', 'sources',
        'followUps', 'insights',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    private function props(User $user): array
    {
        return $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('page')['props'];
    }

    private function widgetsIn(array $props): array
    {
        return array_values(array_intersect(self::WIDGETS, array_keys($props)));
    }

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
        return [
            'Admin' => ['Admin', [...self::REVENUE, ...self::LEADS, 'followUps', 'insights']],
            'Sales Manager' => ['Sales Manager', [...self::REVENUE, ...self::LEADS, 'followUps', 'insights']],
            'Sales Rep' => ['Sales Rep', [...self::LEADS, 'followUps', 'insights']],
            'Viewer' => ['Viewer', [...self::LEADS, 'followUps']],
        ];
    }

    #[DataProvider('widgetsByRole')]
    public function test_each_role_receives_exactly_its_widgets(string $role, array $expected): void
    {
        $props = $this->props($this->userWithRole($role));

        $this->assertEqualsCanonicalizing($expected, $this->widgetsIn($props));
        $this->assertArrayHasKey('period', $props);
    }

    public function test_roles_without_revenue_permission_get_no_money_at_all(): void
    {
        $service = app(DashboardService::class);
        $money = [
            $service->pipelineGoal()['total'],
            $service->weeklyRevenue()['amount'],
            $service->revenueGoal()['closed_won'],
            $service->revenueGoal()['target'],
        ];

        foreach (['Sales Rep', 'Viewer'] as $role) {
            $props = $this->props($this->userWithRole($role));
            $json = json_encode($props);

            foreach (self::REVENUE as $widget) {
                $this->assertArrayNotHasKey($widget, $props, "{$role} must not receive {$widget}");
            }

            foreach ($money as $amount) {
                $this->assertStringNotContainsString((string) (int) $amount, $json);
            }

            foreach ($props['activity'] as $row) {
                $this->assertArrayNotHasKey('value', $row, "{$role} must not receive lead values");
            }
        }
    }

    public function test_roles_with_revenue_permission_get_lead_values(): void
    {
        foreach (['Admin', 'Sales Manager'] as $role) {
            $props = $this->props($this->userWithRole($role));

            foreach ($props['activity'] as $row) {
                $this->assertArrayHasKey('value', $row);
            }
        }
    }

    public function test_ai_insights_are_only_sent_with_the_ai_insights_permission(): void
    {
        $summary = app(DashboardService::class)->insights()['summary'];

        $viewer = $this->props($this->userWithRole('Viewer'));
        $this->assertArrayNotHasKey('insights', $viewer);
        $this->assertStringNotContainsString($summary, json_encode($viewer));

        $rep = $this->props($this->userWithRole('Sales Rep'));
        $this->assertSame($summary, $rep['insights']['summary']);
    }

    public function test_widgets_follow_individual_permissions_immediately(): void
    {
        $user = $this->userWithRole('Viewer');

        $this->assertArrayNotHasKey('insights', $this->props($user));

        Role::findByName('Viewer')->givePermissionTo('ai.insights');
        $this->assertArrayHasKey('insights', $this->props($user->fresh()));

        Role::findByName('Viewer')->revokePermissionTo(['leads.view', 'tasks.view']);
        $widgets = $this->widgetsIn($this->props($user->fresh()));
        $this->assertSame(['insights'], $widgets);
    }

    public function test_a_user_with_only_dashboard_view_receives_no_widget_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.view');

        $this->assertSame([], $this->widgetsIn($this->props($user)));
    }

    public function test_props_never_contain_other_users_or_secrets(): void
    {
        $other = $this->userWithRole('Admin', ['email' => 'someone-else@example.com', 'name' => 'Someone Else']);
        $me = $this->userWithRole('Sales Manager', ['email' => 'me@example.com']);

        foreach ([$me, $other] as $user) {
            $props = $this->props($user);
            $json = json_encode($props);

            $this->assertStringNotContainsString($user->is($me) ? 'someone-else@example.com' : 'me@example.com', $json);
            $this->assertStringNotContainsString($user->password, $json);
            $this->assertStringNotContainsString('remember_token', $json);
            $this->assertStringNotContainsString('"password"', $json);
            $this->assertStringNotContainsString('APP_KEY', $json);
        }

        $props = $this->props($me);
        $this->assertSame(['id' => $me->id, 'name' => $me->name, 'email' => 'me@example.com'], $props['auth']['user']);
    }

    public function test_placeholder_data_is_the_same_for_every_user(): void
    {
        $a = $this->props($this->userWithRole('Sales Manager'));
        $b = $this->props($this->userWithRole('Sales Manager'));

        unset($a['auth'], $b['auth']);

        $this->assertEquals($a, $b);
    }

    public function test_money_is_sent_as_plain_numbers_without_a_currency_symbol(): void
    {
        $json = json_encode($this->props($this->userWithRole('Admin')));

        $this->assertStringNotContainsString('$', $json);
        $this->assertStringNotContainsString('Php', $json);
    }
}
