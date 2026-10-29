<?php

namespace App\Http\Controllers;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Http\Requests\Leads\BulkDeleteLeadsRequest;
use App\Http\Requests\Leads\LeadFormRequest;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\User;
use App\Queries\LeadQuery;
use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsFailure;
use App\Services\Saims\SaimsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    use AuthorizesRequests;

    private const PER_PAGE = 15;

    public function index(Request $request): Response|RedirectResponse
    {
        $this->authorize('viewAny', Lead::class);

        return $this->render($request);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Lead::class);

        return $this->render($request, ['mode' => 'create', 'lead' => null]);
    }

    public function show(Request $request, string $lead): Response|RedirectResponse
    {
        $lead = $this->resolve($lead, 'view');

        return $this->render($request, ['mode' => 'show', 'lead' => LeadResource::detail($lead->load('owner:id,name'), $request->user())]);
    }

    public function edit(Request $request, string $lead): Response|RedirectResponse
    {
        $lead = $this->resolve($lead, 'update');

        return $this->render($request, ['mode' => 'edit', 'lead' => LeadResource::detail($lead->load('owner:id,name'), $request->user())]);
    }

    public function store(StoreLeadRequest $request, SaimsService $saims): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $user = $request->user();

        $lead = new Lead($request->safe()->except('owner_id'));
        $lead->value ??= 0;
        // The creator owns the lead, unless a user with view-all picks someone else.
        $lead->owner_id = $request->mayAssignOwner()
            ? ($request->validated('owner_id') ?? $user->id)
            : $user->id;
        $this->applySaimsLink($lead, $request, $saims);
        $lead->save();

        return to_route('leads.show', [$lead, ...LeadQuery::fromRequest($request)->queryParams()])
            ->with('success', "Lead {$lead->name} was added.");
    }

    public function update(UpdateLeadRequest $request, SaimsService $saims, string $lead): RedirectResponse
    {
        $lead = $this->resolve($lead, 'update');

        $lead->fill($request->safe()->except('owner_id'));
        $lead->value ??= 0;

        // Only a user with view-all can reassign; for everyone else owner_id is ignored.
        if ($request->mayAssignOwner() && $request->validated('owner_id')) {
            $lead->owner_id = $request->validated('owner_id');
        }

        $this->applySaimsLink($lead, $request, $saims);

        $lead->save();

        return to_route('leads.show', [$lead, ...LeadQuery::fromRequest($request)->queryParams()])
            ->with('success', "Lead {$lead->name} was updated.");
    }

    public function destroy(Request $request, string $lead): RedirectResponse
    {
        $lead = $this->resolve($lead, 'delete');

        $lead->delete();

        return to_route('leads.index', LeadQuery::fromRequest($request)->queryParams())
            ->with('success', "Lead {$lead->name} was deleted.");
    }

    /**
     * Applies the "SAIMS customer" field of the form.
     *
     * Only for a user with saims.customers (for anyone else the field does not
     * exist and is ignored). A new link is checked against SAIMS, and the name
     * saved is SAIMS's own, never the one the browser sent. Unlinking clears both
     * columns. An unchanged link makes no call.
     *
     * @throws ValidationException
     */
    private function applySaimsLink(Lead $lead, LeadFormRequest $request, SaimsService $saims): void
    {
        if (! $request->mayLinkSaims() || ! $request->has('saims_customer_id')) {
            return;
        }

        $id = $request->validated('saims_customer_id');

        if ($id === null) {
            $lead->saims_customer_id = null;
            $lead->saims_customer_name = null;

            return;
        }

        if ((int) $lead->saims_customer_id === (int) $id) {
            return;
        }

        try {
            $customer = $saims->customerById((int) $id);
        } catch (SaimsException $e) {
            throw ValidationException::withMessages(['saims_customer_id' => $e->failure === SaimsFailure::NotFound
                ? 'That customer was not found in SAIMS.'
                : 'SAIMS could not be checked right now, so the customer was not linked. Please try again in a moment.']);
        }

        $lead->saims_customer_id = $customer['id'];
        $lead->saims_customer_name = $customer['customer_name'];
    }

    /**
     * Deletes the selected leads that are in the user's scope. Ids outside it
     * are ignored, exactly as if they did not exist.
     */
    public function bulkDestroy(BulkDeleteLeadsRequest $request): RedirectResponse
    {
        $this->authorize('deleteAny', Lead::class);

        $user = $request->user();

        $deleted = Lead::query()
            ->visibleTo($user)
            ->whereIn('id', $request->validated('ids'))
            ->get()
            ->filter(fn (Lead $lead) => $user->can('delete', $lead))
            ->each->delete()
            ->count();

        return to_route('leads.index', LeadQuery::fromRequest($request)->queryParams())
            ->with('success', $deleted === 1 ? '1 lead was deleted.' : "{$deleted} leads were deleted.");
    }

    /**
     * CSV of the current filtered, in-scope list.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Lead::class);

        $query = LeadQuery::fromRequest($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads the names correctly

            fputcsv($out, ['Name', 'Company', 'Email', 'Phone', 'Stage', 'Priority', 'Source', 'Value (Php)', 'Assigned to', 'Created', 'Updated']);

            $query->sorted($query->filtered())->with('owner:id,name')->lazyById(200)->each(function (Lead $lead) use ($out) {
                fputcsv($out, array_map(self::csvSafe(...), [
                    $lead->name,
                    $lead->company,
                    $lead->email,
                    $lead->phone,
                    $lead->stage->label(),
                    $lead->priority->label(),
                    $lead->source->label(),
                    $lead->value,
                    $lead->owner?->name,
                    $lead->created_at?->toDateTimeString(),
                    $lead->updated_at?->toDateTimeString(),
                ]));
            });

            fclose($out);
        }, 'leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Defuses spreadsheet formulas: a cell starting with = + - @ (or a tab or
     * carriage return) is prefixed with an apostrophe.
     */
    private static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Finds a lead and authorizes it. Order matters: leads.view first (403),
     * then existence and scope (404), then the ability (403).
     */
    private function resolve(string $id, string $ability): Lead
    {
        $this->authorize('viewAny', Lead::class);

        $lead = Lead::findOrFail($id);

        $this->authorize($ability, $lead);

        return $lead;
    }

    /**
     * The Leads screen, optionally with the drawer open (detail, edit or create).
     * Filters, search, sort and page come from the query string.
     *
     * @param  array{mode: string, lead: ?array<string, mixed>}|null  $drawer
     */
    private function render(Request $request, ?array $drawer = null): Response|RedirectResponse
    {
        $user = $request->user();
        $query = LeadQuery::fromRequest($request);

        $leads = $query->sorted($query->filtered())
            ->with('owner:id,name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // e.g. the last lead of the last page was just deleted
        if ($drawer === null && $leads->isEmpty() && $leads->currentPage() > 1) {
            return to_route('leads.index', [...$query->queryParams(), 'page' => $leads->lastPage()]);
        }

        return Inertia::render('Leads/Index', [
            'leads' => $leads->through(fn (Lead $lead) => LeadResource::row($lead, $user)),
            'filters' => $query->filters,
            'stageCounts' => $query->stageCounts(),
            // Money aggregates are only computed and sent with dashboard.view-revenue.
            'stats' => $query->stats(withMoney: $user->can('dashboard.view-revenue')),
            'options' => [
                'stages' => LeadStage::options(),
                'priorities' => LeadPriority::options(),
                'sources' => LeadSource::options(),
            ],
            'drawer' => $drawer,
            'owners' => $this->assignableOwners($user, $drawer['lead']['owner'] ?? null),
        ]);
    }

    /**
     * The "Assigned to" choices: only for a user who may assign (view-all) and
     * who can actually create or edit. Everyone else gets an empty list.
     *
     * @param  array{id: int, name: string}|null  $current  Kept in the list even if deactivated.
     * @return list<array{id: int, name: string}>
     */
    private function assignableOwners(User $user, ?array $current): array
    {
        if (! $user->can(Lead::viewAllPermission()) || ! ($user->can('leads.create') || $user->can('leads.update'))) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->when($current, fn ($query) => $query->orWhere('id', $current['id']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $owner) => ['id' => $owner->id, 'name' => $owner->name])
            ->all();
    }
}
