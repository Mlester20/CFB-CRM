<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     *
     * The permission checks happen here: a widget the user may not see is never
     * queried and its data is never sent to the browser. The frontend only
     * reflects which props arrived. Lead data always goes through the user's own/all
     * scope, inside DashboardService.
     */
    public function __invoke(DashboardRequest $request, DashboardService $dashboard): Response
    {
        $user = $request->user();
        $range = $request->range();

        $canRevenue = $user->can('dashboard.view-revenue');
        $canLeads = $user->can('leads.view');

        $props = ['period' => $range->toArray()];

        if ($canRevenue) {
            $props['pipeline'] = $dashboard->pipelineGoal($user, $range);

            if ($user->can('saims.invoices')) {
                // Invoiced revenue comes from SAIMS. It can be slow, so it is loaded after
                // the page opens (deferred): the rest of the dashboard never waits for it.
                $props['revenueSource'] = 'saims';
                $props['weeklyRevenue'] = Inertia::defer(fn () => $dashboard->saimsWeeklyRevenue());
                $props['revenueGoal'] = Inertia::defer(fn () => $dashboard->saimsRevenueGoal($range));
            } else {
                // No access to SAIMS invoices: fall back to the value of won leads.
                $props['revenueSource'] = 'leads';
                $props['weeklyRevenue'] = $dashboard->leadWeeklyRevenue($user);
                $props['revenueGoal'] = $dashboard->leadRevenueGoal($user, $range);
            }
        }

        if ($canLeads) {
            $props['conversion'] = $dashboard->conversion($user, $range);
            $props['engagement'] = $dashboard->engagement($user, $range);
            // The deal value is money, so it follows the revenue permission.
            $props['activity'] = $dashboard->leadActivity($user, $range, withValue: $canRevenue);
            $props['sources'] = $dashboard->leadSources($user, $range);
        }

        if ($user->can('contacts.view')) {
            $props['topContacts'] = $dashboard->topContacts(); // PLACEHOLDER
        }

        if ($user->can('deals.view')) {
            // Both carry deal values, which are money: only with the revenue permission.
            $props['pipelineStages'] = $dashboard->pipelineStages($user, $range, withValue: $canRevenue);
            $props['topOpenDeals'] = $dashboard->topOpenDeals($user, $range, withValue: $canRevenue);
        }

        if ($user->can('tasks.view')) {
            $props['followUps'] = $dashboard->followUps(); // PLACEHOLDER
        }

        if ($user->can('ai.insights')) {
            $props['insights'] = $dashboard->insights(); // PLACEHOLDER
        }

        return Inertia::render('Dashboard', $props);
    }
}
