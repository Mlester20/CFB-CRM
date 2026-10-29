<?php

namespace App\Http\Controllers;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Http\Requests\Pipeline\ColumnRequest;
use App\Http\Requests\Pipeline\MoveLeadRequest;
use App\Http\Resources\LeadResource;
use App\Http\Resources\PipelineResource;
use App\Models\Lead;
use App\Queries\PipelineQuery;
use App\Services\Pipeline\PipelineMover;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Pipeline: a drag-and-drop board over the leads table (a deal IS a lead, its
 * stage is the column). Needs deals.view; moving a card needs deals.move. The scope
 * is the leads scope keyed on deals.view-all.
 */
class PipelineController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewPipeline', Lead::class);

        return $this->render($request);
    }

    /** The board with a lead open in the drawer (the same drawer as the Leads page). */
    public function show(Request $request, string $lead): Response
    {
        $this->authorize('viewPipeline', Lead::class);

        $lead = Lead::findOrFail($lead);
        $this->authorize('viewInPipeline', $lead);

        return $this->render($request, $lead);
    }

    /** "Show more": the next cards of one column. */
    public function column(ColumnRequest $request): JsonResponse
    {
        $query = new PipelineQuery($request->user(), [
            'search' => (string) $request->validated('search', ''),
            'priority' => $request->validated('priority'),
        ]);

        $cards = $query->cards(LeadStage::from($request->validated('stage')), (int) $request->validated('offset'), PipelineQuery::PER_COLUMN + 1);

        return response()->json([
            'cards' => $cards->take(PipelineQuery::PER_COLUMN)->map(PipelineResource::card(...))->values(),
            'has_more' => $cards->count() > PipelineQuery::PER_COLUMN,
        ]);
    }

    /**
     * Moves a card to a column and position (the index among the cards the user sees).
     * Answers with the fresh totals of the columns involved and the stat cards, so the
     * board can reconcile its optimistic update.
     */
    public function move(MoveLeadRequest $request, PipelineMover $mover, string $lead): JsonResponse
    {
        $user = $request->user();
        $lead = Lead::findOrFail($lead);

        $this->authorize('move', $lead);

        $result = $mover->move($lead, $user, LeadStage::from($request->validated('stage')), (int) $request->validated('position'));

        $query = new PipelineQuery($user, [
            'search' => (string) ($request->validated('search') ?? ''),
            'priority' => $request->validated('priority'),
        ]);
        $withMoney = $user->can('dashboard.view-revenue');
        $stages = array_values(array_unique([$result['from'], $result['to']], SORT_REGULAR));

        return response()->json([
            'lead' => ['id' => $lead->id, 'stage' => $result['to']->value, 'position' => $lead->refresh()->pipeline_position],
            'columns' => $query->columnTotals($withMoney, $stages),
            'stats' => $query->stats($withMoney),
        ]);
    }

    private function render(Request $request, ?Lead $drawerLead = null): Response
    {
        $user = $request->user();
        $query = PipelineQuery::fromRequest($request);
        $withMoney = $user->can('dashboard.view-revenue');

        return Inertia::render('Pipeline/Index', [
            'filters' => $query->filters,
            'options' => [
                'priorities' => LeadPriority::options(),
                'stages' => LeadStage::options(),
                'sources' => LeadSource::options(),
            ],
            'canMove' => $user->can('deals.move'),
            // Lazy: opening a card in the drawer is a partial visit that skips the board.
            'board' => fn () => $this->board($query, $withMoney),
            'stats' => fn () => $query->stats($withMoney),
            'drawer' => fn () => $drawerLead
                ? ['lead' => LeadResource::detail($drawerLead->load('owner:id,name'), $user)]
                : null,
        ]);
    }

    /**
     * The five columns. Counts and values follow the filters; the values (money) are
     * only computed and sent with dashboard.view-revenue.
     *
     * @return list<array<string, mixed>>
     */
    private function board(PipelineQuery $query, bool $withMoney): array
    {
        $totals = $query->columnTotals($withMoney);
        $columns = [];

        foreach (LeadStage::cases() as $stage) {
            $cards = $query->cards($stage);
            $total = $totals[$stage->value];

            $columns[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $total['count'],
                ...($withMoney ? ['value' => $total['value']] : []),
                'cards' => $cards->map(PipelineResource::card(...))->values()->all(),
                'has_more' => $total['count'] > $cards->count(),
            ];
        }

        return $columns;
    }
}
