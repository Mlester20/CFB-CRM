<?php

namespace App\Queries;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Everything the Leads screen, the stage chips, the stat cards and the CSV
 * export read from the leads table. Every query starts from visibleTo($user),
 * so nothing here can return a lead outside the user's own/all scope.
 */
class LeadQuery
{
    /** Sortable columns: query-string value => database column. */
    public const SORTS = ['name' => 'name', 'value' => 'value', 'updated' => 'updated_at'];

    /**
     * @param  array{search: string, stage: ?string, priority: ?string, source: ?string, sort: string, direction: string}  $filters
     */
    public function __construct(
        private readonly User $user,
        public readonly array $filters,
    ) {}

    /**
     * Reads and sanitises the filters from the query string. Anything
     * unknown is dropped, so the client can never inject a column name.
     */
    public static function fromRequest(Request $request): self
    {
        $sort = (string) $request->query('sort', 'updated');
        $sort = array_key_exists($sort, self::SORTS) ? $sort : 'updated';

        $direction = strtolower((string) $request->query('direction', $sort === 'name' ? 'asc' : 'desc'));

        return new self($request->user(), [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 100),
            'stage' => LeadStage::tryFrom((string) $request->query('stage'))?->value,
            'priority' => LeadPriority::tryFrom((string) $request->query('priority'))?->value,
            'source' => LeadSource::tryFrom((string) $request->query('source'))?->value,
            'sort' => $sort,
            'direction' => $direction === 'asc' ? 'asc' : 'desc',
        ]);
    }

    /**
     * In scope, with search, priority and source applied but NOT the stage,
     * so the stage chips can show how many leads each stage would give.
     *
     * @return Builder<Lead>
     */
    public function base(): Builder
    {
        $search = $this->filters['search'];

        return Lead::query()
            ->visibleTo($this->user)
            ->when($search !== '', function (Builder $query) use ($search) {
                // "!" is the escape character: it behaves the same on MySQL and SQLite,
                // so a typed % or _ is matched literally instead of acting as a wildcard.
                $like = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

                $query->whereRaw(
                    "(name like ? escape '!' or company like ? escape '!' or email like ? escape '!')",
                    [$like, $like, $like],
                );
            })
            ->when($this->filters['priority'], fn (Builder $q, string $priority) => $q->where('priority', $priority))
            ->when($this->filters['source'], fn (Builder $q, string $source) => $q->where('source', $source));
    }

    /**
     * The list the user is looking at: base() plus the stage filter.
     *
     * @return Builder<Lead>
     */
    public function filtered(): Builder
    {
        return $this->base()->when(
            $this->filters['stage'],
            fn (Builder $q, string $stage) => $q->where('stage', $stage),
        );
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function sorted(Builder $query): Builder
    {
        return $query
            ->orderBy(self::SORTS[$this->filters['sort']], $this->filters['direction'])
            ->orderByDesc('id');
    }

    /**
     * Lead count per stage (and "all") for the chips.
     *
     * @return array<string, int>
     */
    public function stageCounts(): array
    {
        $counts = $this->base()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $result = ['all' => (int) $counts->sum()];

        foreach (LeadStage::cases() as $stage) {
            $result[$stage->value] = (int) ($counts[$stage->value] ?? 0);
        }

        return $result;
    }

    /**
     * Stat cards for everything in the user's scope (not affected by filters).
     *
     * The money aggregates are only computed, and so only ever sent, when
     * $withMoney is true (dashboard.view-revenue).
     *
     * @return array<string, int|float>
     */
    public function stats(bool $withMoney): array
    {
        $scope = Lead::query()->visibleTo($this->user);

        if (! $withMoney) {
            return ['total' => $scope->count()];
        }

        $open = "'".implode("','", LeadStage::open())."'";
        $row = $scope
            ->selectRaw("count(*) as total, coalesce(sum(value), 0) as total_value, coalesce(sum(case when stage in ({$open}) then value else 0 end), 0) as open_value, coalesce(sum(case when stage = 'won' then value else 0 end), 0) as won_value")
            ->first();

        $total = (int) $row->total;

        return [
            'total' => $total,
            'open_pipeline' => (float) $row->open_value,
            'won_value' => (float) $row->won_value,
            // Total value of all in-scope leads divided by the number of leads.
            'avg_deal_size' => $total > 0 ? round((float) $row->total_value / $total, 2) : 0.0,
        ];
    }

    /**
     * The non-default filters, to keep the state in redirects and links.
     *
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        $params = array_filter(
            Arr::only($this->filters, ['search', 'stage', 'priority', 'source']),
            fn (?string $value) => $value !== null && $value !== '',
        );

        if ($this->filters['sort'] !== 'updated' || $this->filters['direction'] !== 'desc') {
            $params['sort'] = $this->filters['sort'];
            $params['direction'] = $this->filters['direction'];
        }

        return $params;
    }
}
