<?php

namespace App\Queries;

use App\Enums\LeadPriority;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Everything the Pipeline board reads. The Pipeline is a second view of the leads
 * table, but its scope is keyed on the DEALS permission: a user with
 * deals.view-all sees every lead, everyone else only the leads they own.
 */
class PipelineQuery
{
    /** Cards loaded per column; "Show more" fetches the next ones. */
    public const PER_COLUMN = 30;

    /**
     * @param  array{search: string, priority: ?string}  $filters
     */
    public function __construct(
        private readonly User $user,
        public readonly array $filters,
    ) {}

    /** Reads and sanitises the filters; anything unknown is dropped. */
    public static function fromRequest(Request $request): self
    {
        return new self($request->user(), [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 100),
            'priority' => LeadPriority::tryFrom((string) $request->query('priority'))?->value,
        ]);
    }

    /**
     * Everything the user may see on the board, without the search and priority filters.
     *
     * @return Builder<Lead>
     */
    public function scope(): Builder
    {
        return Lead::query()->visibleTo($this->user, LeadPolicy::PIPELINE_VIEW_ALL);
    }

    /**
     * What the columns show: the scope plus the search and priority filters.
     *
     * @return Builder<Lead>
     */
    public function filtered(): Builder
    {
        return $this->scope()
            ->when($this->filters['search'] !== '', fn (Builder $q) => $q->matching($this->filters['search']))
            ->when($this->filters['priority'], fn (Builder $q, string $priority) => $q->where('priority', $priority));
    }

    public function filtersActive(): bool
    {
        return $this->filters['search'] !== '' || $this->filters['priority'] !== null;
    }

    /**
     * A page of cards of one column, in board order.
     *
     * @return Collection<int, Lead>
     */
    public function cards(LeadStage $stage, int $offset = 0, int $limit = self::PER_COLUMN): Collection
    {
        return $this->filtered()
            ->where('stage', $stage->value)
            ->orderBy('pipeline_position')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Count (and, with $withMoney, value) of the filtered leads in each column.
     * The value is money: only computed and returned when $withMoney.
     *
     * @param  list<LeadStage>|null  $stages  only these columns (all by default)
     * @return array<string, array{count: int, value?: float}>
     */
    public function columnTotals(bool $withMoney, ?array $stages = null): array
    {
        $query = $this->filtered()->toBase()
            ->selectRaw($withMoney ? 'stage, count(*) as total, coalesce(sum(value), 0) as amount' : 'stage, count(*) as total')
            ->groupBy('stage');

        if ($stages !== null) {
            $query->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, $stages));
        }

        $rows = $query->get()->keyBy('stage');

        $totals = [];
        foreach ($stages ?? LeadStage::cases() as $stage) {
            $row = $rows->get($stage->value);
            $totals[$stage->value] = ['count' => (int) ($row->total ?? 0)];

            if ($withMoney) {
                $totals[$stage->value]['value'] = round((float) ($row->amount ?? 0), 2);
            }
        }

        return $totals;
    }

    /**
     * The four stat cards, for everything in the user's scope (not affected by the
     * filters). The money ones are only computed and sent with $withMoney.
     *
     * @return array<string, int|float>
     */
    public function stats(bool $withMoney): array
    {
        $rows = $this->scope()->toBase()
            ->selectRaw('stage, count(*) as total, coalesce(sum(value), 0) as amount')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $count = fn (string $stage) => (int) ($rows->get($stage)->total ?? 0);
        $value = fn (string $stage) => (float) ($rows->get($stage)->amount ?? 0);

        $open = array_map(fn (string $s) => $count($s), LeadStage::open());
        $won = $count('won');
        $lost = $count('lost');

        $stats = [
            'total_leads' => (int) $rows->sum('total'),
            'open_deals' => (int) array_sum($open),
            'win_rate' => $won + $lost > 0 ? round($won / ($won + $lost) * 100, 1) : 0.0,
        ];

        if ($withMoney) {
            $stats['total_pipeline'] = round((float) $rows->sum('amount'), 2);
            $stats['open_value'] = round(array_sum(array_map(fn (string $s) => $value($s), LeadStage::open())), 2);
            $stats['won_value'] = round($value('won'), 2);
        }

        return $stats;
    }
}
