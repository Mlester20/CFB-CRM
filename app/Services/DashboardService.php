<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Supplies the data for the dashboard widgets.
 *
 * PLACEHOLDER: replace with real queries when the modules exist.
 *
 * Every method below returns hard-coded sample data, identical for every user,
 * shaped like the final props. When the Leads / Deals / Tasks / AI modules are
 * built, only the bodies of these methods should change: the controller, the
 * permission checks and the React widgets stay as they are.
 *
 * Money is returned as plain numbers in pesos (no currency symbol); the
 * frontend formats it with formatPhp().
 */
class DashboardService
{
    /**
     * The period shown in the (display-only) date-range chip.
     *
     * @return array{from: string, to: string}
     */
    public function period(): array
    {
        $year = now()->year;

        return ['from' => "{$year}-01-01", 'to' => "{$year}-12-31"];
    }

    /**
     * Total value of the open pipeline. Money: needs dashboard.view-revenue.
     *
     * @return array{total: float, deals_count: int}
     */
    public function pipelineGoal(): array
    {
        return ['total' => 4102000.00, 'deals_count' => 48];
    }

    /**
     * Revenue of the last 7 days. Money: needs dashboard.view-revenue.
     *
     * @return array{amount: float, change_percent: float}
     */
    public function weeklyRevenue(): array
    {
        return ['amount' => 710000.00, 'change_percent' => 12.4];
    }

    /**
     * Closed-won revenue against the target, plus a monthly series.
     * Money: needs dashboard.view-revenue.
     *
     * @return array{closed_won: float, target: float, change_percent: float, series: list<array{label: string, value: float}>}
     */
    public function revenueGoal(): array
    {
        $values = [180000, 240000, 210000, 320000, 290000, 410000, 380000, 450000, 0, 0, 0, 0];

        return [
            'closed_won' => 2850000.00,
            'target' => 4000000.00,
            'change_percent' => 8.2,
            'series' => $this->monthSeries($values, upTo: 8),
        ];
    }

    /**
     * Win rate and counters. Needs leads.view.
     *
     * @return array{win_rate: float, change_percent: float, leads: int, open_tasks: int}
     */
    public function conversion(): array
    {
        return ['win_rate' => 32.5, 'change_percent' => 4.1, 'leads' => 128, 'open_tasks' => 17];
    }

    /**
     * New leads per month and per year. Needs leads.view.
     *
     * `highlight` is the index of the bar that shows the tooltip by default;
     * `change_percent` is the change against the previous bar.
     *
     * @return array{monthly: array{highlight: int, bars: list<array<string, mixed>>}, annually: array{highlight: int, bars: list<array<string, mixed>>}}
     */
    public function engagement(): array
    {
        $months = [34, 41, 38, 52, 47, 63, 58, 71, 66, 80, 74, 92];
        $years = [310, 365, 402, 478, 540];
        $firstYear = now()->year - count($years) + 1;

        return [
            'monthly' => [
                'highlight' => now()->month - 1,
                'bars' => $this->withChange(array_map(
                    fn (int $i) => ['label' => Carbon::create(null, $i + 1, 1)->format('M'), 'value' => $months[$i]],
                    array_keys($months),
                )),
            ],
            'annually' => [
                'highlight' => count($years) - 1,
                'bars' => $this->withChange(array_map(
                    fn (int $i) => ['label' => (string) ($firstYear + $i), 'value' => $years[$i]],
                    array_keys($years),
                )),
            ],
        ];
    }

    /**
     * Recent lead activity. Needs leads.view.
     *
     * The deal value is money, so it is only included with $withValue
     * (dashboard.view-revenue).
     *
     * @return list<array<string, mixed>>
     */
    public function leadActivity(bool $withValue): array
    {
        $rows = [
            ['Maria Santos', 'Santos Trading', 0, '09:15', 'New', 85000],
            ['Juan Dela Cruz', 'Dela Cruz Logistics', 1, '14:40', 'Qualified', 240000],
            ['Angela Reyes', 'Reyes & Co.', 2, '11:05', 'Proposal', 520000],
            ['Paolo Garcia', 'Garcia Foods', 3, '16:20', 'Won', 310000],
            ['Liza Mendoza', 'Mendoza Retail', 5, '10:30', 'Lost', 150000],
            ['Carlo Ramos', 'Ramos Builders', 6, '13:55', 'Qualified', 98000],
        ];

        return array_map(function (array $row, int $index) use ($withValue) {
            [$name, $company, $daysAgo, $time, $status, $value] = $row;

            $item = [
                'id' => $index + 1,
                'name' => $name,
                'company' => $company,
                'date' => now()->subDays($daysAgo)->toDateString(),
                'time' => $time,
                'status' => $status,
            ];

            if ($withValue) {
                $item['value'] = (float) $value;
            }

            return $item;
        }, $rows, array_keys($rows));
    }

    /**
     * Leads grouped by source. Needs leads.view.
     *
     * @return array{total: int, items: list<array{label: string, value: int}>}
     */
    public function leadSources(): array
    {
        $items = [
            ['label' => 'Website', 'value' => 52],
            ['label' => 'Referral', 'value' => 31],
            ['label' => 'Social media', 'value' => 24],
            ['label' => 'Cold outreach', 'value' => 14],
            ['label' => 'Events', 'value' => 7],
        ];

        return ['total' => array_sum(array_column($items, 'value')), 'items' => $items];
    }

    /**
     * Follow-ups due soon. Needs tasks.view.
     *
     * @return list<array{id: int, title: string, contact: string, due_date: string, overdue: bool}>
     */
    public function followUps(): array
    {
        $rows = [
            ['Send revised quotation', 'Angela Reyes', -2],
            ['Call about contract terms', 'Paolo Garcia', 0],
            ['Schedule product demo', 'Maria Santos', 1],
            ['Follow up on proposal', 'Juan Dela Cruz', 3],
        ];

        return array_map(fn (array $row, int $index) => [
            'id' => $index + 1,
            'title' => $row[0],
            'contact' => $row[1],
            'due_date' => now()->addDays($row[2])->toDateString(),
            'overdue' => $row[2] < 0,
        ], $rows, array_keys($rows));
    }

    /**
     * Short AI-written pipeline insights. Needs ai.insights.
     *
     * Note for the real implementation: the generated text must not mention
     * revenue figures unless the user also has dashboard.view-revenue.
     *
     * @return array{summary: string, highlights: list<string>}
     */
    public function insights(): array
    {
        return [
            'summary' => 'Your pipeline is healthy. Most new leads come from your website and referrals, and qualified leads are moving to proposals faster than last month.',
            'highlights' => [
                'Referral leads convert better than any other source.',
                'Two follow-ups are overdue and may stall open deals.',
                'Proposals older than a week are the biggest risk to the win rate.',
            ],
        ];
    }

    /**
     * @param  list<int|float>  $values
     * @return list<array{label: string, value: float}>
     */
    private function monthSeries(array $values, int $upTo): array
    {
        $series = [];

        foreach (array_slice($values, 0, $upTo) as $i => $value) {
            $series[] = ['label' => Carbon::create(null, $i + 1, 1)->format('M'), 'value' => (float) $value];
        }

        return $series;
    }

    /**
     * Adds the change against the previous bar, in percent.
     *
     * @param  list<array{label: string, value: int}>  $bars
     * @return list<array<string, mixed>>
     */
    private function withChange(array $bars): array
    {
        foreach ($bars as $i => $bar) {
            $previous = $bars[$i - 1]['value'] ?? null;

            $bars[$i]['change_percent'] = $previous
                ? round(($bar['value'] - $previous) / $previous * 100, 1)
                : 0.0;
        }

        return $bars;
    }
}
