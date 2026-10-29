<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Database\Seeders\DevUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The approved matrix, written out independently of App\Support\Rbac. */
    private const ALL = [
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.export',
        'deals.view', 'deals.move', 'deals.update', 'deals.delete',
        'contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete',
        'notes.view', 'notes.create', 'notes.update', 'notes.delete',
        'tasks.view', 'tasks.create', 'tasks.update', 'tasks.complete', 'tasks.delete',
        'dashboard.view', 'dashboard.view-revenue',
        'ai.summary', 'ai.email', 'ai.insights',
        'users.manage', 'roles.manage',
        'saims.customers', 'saims.products', 'saims.orders', 'saims.invoices',
        'leads.view-all', 'deals.view-all', 'contacts.view-all', 'notes.view-all', 'tasks.view-all',
    ];

    public static function matrix(): array
    {
        $manager = array_values(array_diff(self::ALL, ['users.manage', 'roles.manage']));

        return [
            'Admin' => ['Admin', self::ALL],
            'Sales Manager' => ['Sales Manager', $manager],
            'Sales Rep' => ['Sales Rep', [
                'leads.view', 'leads.create', 'leads.update',
                'deals.view', 'deals.move', 'deals.update',
                'contacts.view', 'contacts.create', 'contacts.update',
                'notes.view', 'notes.create', 'notes.update', 'notes.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.complete', 'tasks.delete',
                'dashboard.view',
                'ai.summary', 'ai.email', 'ai.insights',
                'saims.customers', 'saims.products',
            ]],
            'Viewer' => ['Viewer', [
                'leads.view', 'deals.view', 'contacts.view', 'notes.view', 'tasks.view',
                'leads.view-all', 'deals.view-all', 'contacts.view-all', 'notes.view-all', 'tasks.view-all',
                'dashboard.view',
            ]],
        ];
    }

    #[DataProvider('matrix')]
    public function test_each_role_has_exactly_the_approved_permissions(string $role, array $expected): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actual = Role::findByName($role)->permissions->pluck('name')->all();

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_all_permissions_and_exactly_four_roles_are_seeded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(38, Permission::count());
        $this->assertEqualsCanonicalizing(
            ['Admin', 'Sales Manager', 'Sales Rep', 'Viewer'],
            Role::pluck('name')->all(),
        );
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        config(['admin.email' => 'boss@example.com', 'admin.password' => 'secret-pass-123']);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(38, Permission::count());
        $this->assertSame(4, Role::count());
        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $this->assertSame(24, Role::findByName('Sales Rep')->permissions()->count());
    }

    public function test_first_admin_comes_from_config_and_keeps_an_existing_password(): void
    {
        config([
            'admin.email' => 'boss@example.com',
            'admin.password' => 'secret-pass-123',
            'admin.name' => 'The Boss',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertSame('The Boss', $admin->name);
        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify('secret-pass-123', $admin->password));

        $hash = $admin->password;
        config(['admin.password' => 'another-pass-456']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_no_admin_is_created_when_env_values_are_missing(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_dev_users_are_only_created_in_the_local_environment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // The test suite runs in the "testing" environment.
        $this->seed(DevUsersSeeder::class);
        $this->assertSame(0, User::count());

        $this->app['env'] = 'local';
        $this->seed(DevUsersSeeder::class);

        $this->assertSame(3, User::count());
        $this->assertTrue(User::where('email', 'rep@crm.test')->firstOrFail()->hasRole('Sales Rep'));
    }
}
