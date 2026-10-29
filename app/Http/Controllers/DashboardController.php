<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     *
     * The permission checks happen here: a widget the user may not see is never
     * queried and its data is never sent to the browser. The frontend only
     * reflects which props arrived.
     */
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();

        $canRevenue = $user->can('dashboard.view-revenue');
        $canLeads = $user->can('leads.view');

        $props = ['period' => $dashboard->period()];

        if ($canRevenue) {
            $props['pipeline'] = $dashboard->pipelineGoal();
            $props['weeklyRevenue'] = $dashboard->weeklyRevenue();
            $props['revenueGoal'] = $dashboard->revenueGoal();
        }

        if ($canLeads) {
            $props['conversion'] = $dashboard->conversion();
            $props['engagement'] = $dashboard->engagement();
            // The deal value is money, so it follows the revenue permission.
            $props['activity'] = $dashboard->leadActivity(withValue: $canRevenue);
            $props['sources'] = $dashboard->leadSources();
        }

        if ($user->can('contacts.view')) {
            $props['topContacts'] = $dashboard->topContacts();
        }

        if ($user->can('deals.view')) {
            // Both carry deal values, which are money: only with the revenue permission.
            $props['pipelineStages'] = $dashboard->pipelineStages(withValue: $canRevenue);
            $props['topOpenDeals'] = $dashboard->topOpenDeals(withValue: $canRevenue);
        }

        if ($user->can('tasks.view')) {
            $props['followUps'] = $dashboard->followUps();
        }

        if ($user->can('ai.insights')) {
            $props['insights'] = $dashboard->insights();
        }

        return Inertia::render('Dashboard', $props);
    }
}
