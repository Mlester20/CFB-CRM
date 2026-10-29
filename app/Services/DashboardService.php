<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The data of the dashboard widgets.
 *
 *  - Everything about leads is REAL, always read through the user's scope
 *    (Lead::visibleTo) and, except where noted, limited to the leads created in
 *    the selected date range.
 *  - Revenue from SAIMS is "Invoiced (incl. VAT)": the sum of total_sales of the
 *    non-cancelled invoices. Without saims.invoices the revenue widgets fall back
 *    to the value of won leads.
 *  - Top Contacts, Upcoming Follow-ups and AI Insights are still PLACEHOLDERS
 *    (see the bottom of this file): their modules do not exist yet.
 *
 * Money is returned as plain numbers in pesos; the frontend formats it with
 * formatPhp(). Whether the user may see money at all is decided by the
 * controller, which asks for the money methods only with dashboard.view-revenue.
 */
class DashboardService
{
    public function __construct(private readonly SaimsService $saims) {}

    // ========================================================== leads: stat widgets

    /**
     * Value of the open leads. Money: dashboard.view-revenue.
     *
     * @return array{total: float, deals_count: int}
     */
    public function pipelineGoal(User $user, DateRange $range): array
    {
        $totals = $this->stageTotals($user, $range);
        $open = array_intersect_key($totals, array_flip(LeadStage::open()));

        return [
            'total' => round(array_sum(array_column($open, 'value')), 2),
            'deals_count' => (int) array_sum(array_column($open, 'count')),
        ];
    }

    /**
     * Win rate = won / (won + lost), with the change in points against the
     * previous period of the same length (null when either period has no closed lead).
     *
     * @return array{win_rate: float, change_percent: ?float, leads: int, open_leads: int, won: int, lost: int}
     */
    public function conversion(User $user, DateRange $range): array
    {
        $now = $this->stageTotals($user, $range);
        $rate = $this->winRate($now);
        $before = $this->winRate($this->stageTotals($user, $range->previous()));

        return [
            'win_rate' => $rate ?? 0.0,
            'change_percent' => $rate !== null && $before !== null ? round($rate - $before, 1) : null,
            'leads' => (int) array_sum(array_column($now, 'count')),
            'open_leads' => (int) array_sum(array_column(array_intersect_key($now, array_flip(LeadStage::open())), 'count')),
            'won' => $now['won']['count'],
            'lost' => $now['lost']['count'],
        ];
    }

    /**
     * New leads per month (the months of the range, at most the last 12) and per
     * year (the 5 calendar years up to the end of the range, whatever the months
     * selected). `change_percent` is the change against the previous bar.
     *
     * @return array{monthly: array{highlight: int, bars: list<array<string, mixed>>}, annually: array{highlight: int, bars: list<array<string, mixed>>}}
     */
    public function engagement(User $user, DateRange $range): array
    {
        // monthly
        $months = $range->months(12);
        $perMonth = [];

        foreach ($this->leads($user, $range)->pluck('created_at') as $createdAt) {
            $key = $createdAt->format('Y-m');
            $perMonth[$key] = ($perMonth[$key] ?? 0) + 1;
        }

        $sameYear = $months === [] || $months[0]->year === end($months)->year;
        $monthly = array_map(fn (CarbonImmutable $month) => [
            'label' => $month->format($sameYear ? 'M' : "M 'y"),
            'value' => $perMonth[$month->format('Y-m')] ?? 0,
        ], $months);

        $reference = min(CarbonImmutable::now(), $range->to)->format('Y-m');
        $highlight = array_search($reference, array_map(fn ($m) => $m->format('Y-m'), $months), true);

        // yearly
        $lastYear = $range->to->year;
        $firstYear = $lastYear - 4;
        $perYear = [];

        $yearScope = Lead::query()->visibleTo($user)
            ->whereBetween('created_at', [CarbonImmutable::create($firstYear)->startOfYear(), CarbonImmutable::create($lastYear)->endOfYear()]);

        foreach ($yearScope->pluck('created_at') as $createdAt) {
            $perYear[$createdAt->year] = ($perYear[$createdAt->year] ?? 0) + 1;
        }

        $yearly = array_map(fn (int $year) => ['label' => (string) $year, 'value' => $perYear[$year] ?? 0], range($firstYear, $lastYear));

        return [
            'monthly' => ['highlight' => $highlight === false ? max(count($monthly) - 1, 0) : $highlight, 'bars' => $this->withChange($monthly)],
            'annually' => ['highlight' => count($yearly) - 1, 'bars' => $this->withChange($yearly)],
        ];
    }

    /**
     * The most recently changed leads. The deal value is money: only $withValue.
     *
     * @return list<array<string, mixed>>
     */
    public function leadActivity(User $user, DateRange $range, bool $withValue): array
    {
        return $this->leads($user, $range)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (Lead $lead) use ($withValue) {
                $item = [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'company' => $lead->company,
                    'date' => $lead->updated_at->toDateString(),
                    'time' => $lead->updated_at->format('H:i'),
                    'status' => $lead->stage->label(),
                ];

                if ($withValue) {
                    $item['value'] = (float) $lead->value;
                }

                return $item;
            })
            ->all();
    }

    /**
     * Leads by source, largest first.
     *
     * @return array{total: int, items: list<array{label: string, value: int}>}
     */
    public function leadSources(User $user, DateRange $range): array
    {
        $counts = $this->leads($user, $range)->toBase()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->sortDesc();

        $items = [];
        foreach ($counts as $source => $count) {
            $items[] = ['label' => LeadSource::tryFrom($source)?->label() ?? $source, 'value' => (int) $count];
        }

        return ['total' => (int) $counts->sum(), 'items' => $items];
    }

    /**
     * Leads per stage. `percent` is the stage's share of the total: of the total
     * value with $withValue (dashboard.view-revenue), of the lead count otherwise.
     * The peso `value` is money: only $withValue.
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    public function pipelineStages(User $user, DateRange $range, bool $withValue): array
    {
        // The deals widgets follow the deals scope (deals.view-all), like the Pipeline page.
        $totals = $this->stageTotals($user, $range, LeadPolicy::PIPELINE_VIEW_ALL);
        $totalCount = (int) array_sum(array_column($totals, 'count'));
        $totalValue = (float) array_sum(array_column($totals, 'value'));

        $items = [];
        foreach (LeadStage::cases() as $stage) {
            $row = $totals[$stage->value];
            $share = $withValue
                ? ($totalValue > 0 ? $row['value'] / $totalValue : 0)
                : ($totalCount > 0 ? $row['count'] / $totalCount : 0);

            $item = ['stage' => $stage->label(), 'count' => $row['count'], 'percent' => round($share * 100, 1)];

            if ($withValue) {
                $item['value'] = $row['value'];
            }

            $items[] = $item;
        }

        return ['total' => $totalCount, 'items' => $items];
    }

    /**
     * The five biggest open leads. The deal value is money: only $withValue.
     *
     * @return list<array<string, mixed>>
     */
    public function topOpenDeals(User $user, DateRange $range, bool $withValue): array
    {
        return $this->leads($user, $range, LeadPolicy::PIPELINE_VIEW_ALL)
            ->whereIn('stage', LeadStage::open())
            ->orderByDesc('value')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->values()
            ->map(function (Lead $lead, int $index) use ($withValue) {
                $item = [
                    'id' => $lead->id,
                    'rank' => $index + 1,
                    'name' => $lead->name,
                    'company' => $lead->company,
                    'stage' => $lead->stage->label(),
                ];

                if ($withValue) {
                    $item['value'] = (float) $lead->value;
                }

                return $item;
            })
            ->all();
    }

    // =============================================================== revenue widgets
    // All of these are money: the controller only asks for them with dashboard.view-revenue.

    /**
     * Invoiced revenue (SAIMS) of the last 7 days against the 7 before. SAIMS
     * only: needs saims.invoices, and on a SAIMS failure the widget gets an `error`.
     *
     * @return array<string, mixed>
     */
    public function saimsWeeklyRevenue(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $weekStart = $today->subDays(6)->toDateString();

        try {
            $summary = $this->saims->invoiceSummary($today->subDays(13)->toDateString(), $today->toDateString());
        } catch (SaimsException $e) {
            return ['source' => 'saims', 'error' => $e->toArray()];
        }

        $current = 0.0;
        $previous = 0.0;
        foreach ($summary['by_date'] as $date => $amount) {
            $date >= $weekStart ? $current += $amount : $previous += $amount;
        }

        return [
            'source' => 'saims',
            'label' => 'Invoiced (incl. VAT)',
            'amount' => round($current, 2),
            'change_percent' => $this->percentChange($current, $previous),
            'partial' => $summary['partial'],
            'error' => null,
        ];
    }

    /**
     * Invoiced revenue (SAIMS) in the range, with a monthly series and, when
     * CRM_REVENUE_TARGET is set, the target.
     *
     * @return array<string, mixed>
     */
    public function saimsRevenueGoal(DateRange $range): array
    {
        try {
            $summary = $this->saims->invoiceSummary($range->from->toDateString(), $range->to->toDateString());
        } catch (SaimsException $e) {
            return ['source' => 'saims', 'error' => $e->toArray()];
        }

        // The change against the previous period is a bonus: skipped when it cannot be had.
        $change = null;
        if (! $summary['partial']) {
            try {
                $previous = $this->saims->invoiceSummary($range->previous()->from->toDateString(), $range->previous()->to->toDateString());
                $change = $previous['partial'] ? null : $this->percentChange($summary['total'], $previous['total']);
            } catch (SaimsException) {
                $change = null;
            }
        }

        return [
            'source' => 'saims',
            'label' => 'Invoiced (incl. VAT)',
            'amount' => $summary['total'],
            'target' => config('crm.revenue_target'),
            'change_percent' => $change,
            'series' => $this->monthSeries($range, $summary['by_date']),
            'partial' => $summary['partial'],
            'invoices' => $summary['count'],
            'error' => null,
        ];
    }

    /**
     * Without saims.invoices: the value of the leads won in the last 7 days.
     *
     * @return array<string, mixed>
     */
    public function leadWeeklyRevenue(User $user): array
    {
        $today = CarbonImmutable::now();
        $won = fn (CarbonImmutable $from, CarbonImmutable $to) => (float) Lead::query()->visibleTo($user)
            ->where('stage', LeadStage::Won->value)
            ->whereBetween('updated_at', [$from, $to])
            ->sum('value');

        $current = $won($today->subDays(6)->startOfDay(), $today->endOfDay());
        $previous = $won($today->subDays(13)->startOfDay(), $today->subDays(7)->endOfDay());

        return [
            'source' => 'leads',
            'label' => 'Won lead value',
            'amount' => round($current, 2),
            'change_percent' => $this->percentChange($current, $previous),
            'partial' => false,
            'error' => null,
        ];
    }

    /**
     * Without saims.invoices: the value of the leads won in the range.
     *
     * @return array<string, mixed>
     */
    public function leadRevenueGoal(User $user, DateRange $range): array
    {
        $won = fn (DateRange $r) => $this->leads($user, $r)->where('stage', LeadStage::Won->value);

        $byDate = [];
        foreach ($won($range)->get(['created_at', 'value']) as $lead) {
            $date = $lead->created_at->toDateString();
            $byDate[$date] = round(($byDate[$date] ?? 0) + (float) $lead->value, 2);
        }

        $amount = round(array_sum($byDate), 2);

        return [
            'source' => 'leads',
            'label' => 'Won lead value',
            'note' => 'Based on the value of won leads, because your role has no access to SAIMS invoices.',
            'amount' => $amount,
            'target' => config('crm.revenue_target'),
            'change_percent' => $this->percentChange($amount, (float) $won($range->previous())->sum('value')),
            'series' => $this->monthSeries($range, $byDate),
            'partial' => false,
            'error' => null,
        ];
    }

    // ================================================================= helpers

    /**
     * The user's leads created in the range. THE scope of every lead widget.
     *
     * @return Builder<Lead>
     */
    private function leads(User $user, DateRange $range, ?string $viewAll = null): Builder
    {
        return Lead::query()->visibleTo($user, $viewAll)->whereBetween('created_at', [$range->start(), $range->end()]);
    }

    /**
     * Count and value of the leads in each stage (every stage is present).
     *
     * @return array<string, array{count: int, value: float}>
     */
    private function stageTotals(User $user, DateRange $range, ?string $viewAll = null): array
    {
        $rows = $this->leads($user, $range, $viewAll)->toBase()
            ->selectRaw('stage, count(*) as total, coalesce(sum(value), 0) as amount')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $totals = [];
        foreach (LeadStage::cases() as $stage) {
            $row = $rows->get($stage->value);
            $totals[$stage->value] = ['count' => (int) ($row->total ?? 0), 'value' => round((float) ($row->amount ?? 0), 2)];
        }

        return $totals;
    }

    /** @param  array<string, array{count: int, value: float}>  $totals */
    private function winRate(array $totals): ?float
    {
        $closed = $totals['won']['count'] + $totals['lost']['count'];

        return $closed > 0 ? round($totals['won']['count'] / $closed * 100, 1) : null;
    }

    private function percentChange(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /**
     * Totals per month of the range from a date => amount map.
     *
     * @param  array<string, float>  $byDate
     * @return list<array{label: string, value: float}>
     */
    private function monthSeries(DateRange $range, array $byDate): array
    {
        $months = $range->months(12);
        $sameYear = $months === [] || $months[0]->year === end($months)->year;

        return array_map(function (CarbonImmutable $month) use ($byDate, $sameYear) {
            $prefix = $month->format('Y-m');
            $sum = 0.0;

            foreach ($byDate as $date => $amount) {
                if (str_starts_with($date, $prefix)) {
                    $sum += $amount;
                }
            }

            return ['label' => $month->format($sameYear ? 'M' : "M 'y"), 'value' => round($sum, 2)];
        }, $months);
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

    // ============================================================ PLACEHOLDERS
    // PLACEHOLDER: replace with real queries when the modules exist.
    // Contacts, Tasks and the AI feature do not exist yet. These three keep their
    // sample data, identical for every user, behind their own permissions, and the
    // widgets say "Sample data" on screen.

    /**
     * PLACEHOLDER (Tasks module). Needs tasks.view.
     *
     * @return list<array{id: int, title: string, contact: string, due_date: string, overdue: bool, priority: string}>
     */
    public function followUps(): array
    {
        $rows = [
            ['Send revised quotation', 'Angela Reyes', -2, 'High'],
            ['Call about contract terms', 'Paolo Garcia', 0, 'High'],
            ['Schedule product demo', 'Maria Santos', 1, 'Medium'],
            ['Follow up on proposal', 'Juan Dela Cruz', 3, 'Low'],
        ];

        return array_map(fn (array $row, int $index) => [
            'id' => $index + 1,
            'title' => $row[0],
            'contact' => $row[1],
            'due_date' => now()->addDays($row[2])->toDateString(),
            'overdue' => $row[2] < 0,
            'priority' => $row[3],
        ], $rows, array_keys($rows));
    }

    /**
     * PLACEHOLDER (Contacts module). Needs contacts.view.
     *
     * @return array{items: list<array{id: int, name: string}>, more: int}
     */
    public function topContacts(): array
    {
        $names = ['Maria Santos', 'Juan Dela Cruz', 'Angela Reyes', 'Paolo Garcia', 'Liza Mendoza'];

        return [
            'items' => array_map(
                fn (string $name, int $index) => ['id' => $index + 1, 'name' => $name],
                $names,
                array_keys($names),
            ),
            'more' => 22,
        ];
    }

    /**
     * PLACEHOLDER (AI feature). Needs ai.insights.
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
}
