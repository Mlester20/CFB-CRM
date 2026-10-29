<?php

namespace App\Support;

class Rbac
{
    public const ADMIN = 'Admin';

    public const SALES_MANAGER = 'Sales Manager';

    public const SALES_REP = 'Sales Rep';

    public const VIEWER = 'Viewer';

    /**
     * Every permission, grouped by module and in display order.
     *
     * @return array<string, list<string>>
     */
    public static function modules(): array
    {
        return [
            'leads' => ['view', 'create', 'update', 'delete', 'export', 'view-all'],
            'deals' => ['view', 'move', 'update', 'delete', 'view-all'],
            'contacts' => ['view', 'create', 'update', 'delete', 'view-all'],
            'notes' => ['view', 'create', 'update', 'delete', 'view-all'],
            'tasks' => ['view', 'create', 'update', 'complete', 'delete', 'view-all'],
            'dashboard' => ['view', 'view-revenue'],
            'ai' => ['summary', 'email', 'insights'],
            'users' => ['manage'],
            'roles' => ['manage'],
            'saims' => ['customers', 'products', 'orders', 'invoices'],
        ];
    }

    /**
     * Flat list of permission names, e.g. "leads.view".
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        $names = [];

        foreach (self::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * The four fixed roles, in display order.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return [self::ADMIN, self::SALES_MANAGER, self::SALES_REP, self::VIEWER];
    }

    /**
     * Role-to-permission matrix. Admin always receives everything.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $all = self::permissions();

        return [
            self::ADMIN => $all,
            self::SALES_MANAGER => array_values(array_diff($all, ['users.manage', 'roles.manage'])),
            self::SALES_REP => [
                'leads.view', 'leads.create', 'leads.update',
                'deals.view', 'deals.move', 'deals.update',
                'contacts.view', 'contacts.create', 'contacts.update',
                'notes.view', 'notes.create', 'notes.update', 'notes.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.complete', 'tasks.delete',
                'dashboard.view',
                'ai.summary', 'ai.email', 'ai.insights',
                'saims.customers', 'saims.products',
            ],
            self::VIEWER => [
                'leads.view', 'deals.view', 'contacts.view', 'notes.view', 'tasks.view',
                'leads.view-all', 'deals.view-all', 'contacts.view-all', 'notes.view-all', 'tasks.view-all',
                'dashboard.view',
            ],
        ];
    }
}
