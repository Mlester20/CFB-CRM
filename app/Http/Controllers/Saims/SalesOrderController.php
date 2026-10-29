<?php

namespace App\Http\Controllers\Saims;

use App\Http\Requests\Saims\ListSalesOrdersRequest;
use App\Services\Saims\SaimsService;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends SaimsController
{
    protected function permission(): string
    {
        return 'saims.orders';
    }

    public function index(ListSalesOrdersRequest $request, SaimsService $saims): Response
    {
        return $this->render($request, $saims);
    }

    public function show(ListSalesOrdersRequest $request, SaimsService $saims, int $id): Response
    {
        $this->guard($request);

        [$record, $error] = $this->attempt(fn () => $saims->salesOrderById($id), notFoundIs404: true);

        return $this->render($request, $saims, ['id' => $id, 'record' => $record, 'error' => $error]);
    }

    /**
     * @param  array{id: int, record: ?array<string, mixed>, error: ?array<string, string>}|null  $drawer
     */
    private function render(ListSalesOrdersRequest $request, SaimsService $saims, ?array $drawer = null): Response
    {
        $this->guard($request);

        $filters = $request->filters();
        [$list, $error] = $this->attempt(fn () => $saims->salesOrders($filters));

        return Inertia::render('Saims/Orders/Index', [
            'records' => $list,
            'filters' => $filters,
            'error' => $error,
            'drawer' => $drawer,
            'statuses' => SaimsService::ORDER_STATUSES,
            'customers' => $this->customerOptions($request, $saims),
        ]);
    }

    /**
     * The customer filter. It needs the customers endpoint, so it is only
     * offered (and only called) for a user who also has saims.customers.
     *
     * @return list<array{id: int, name: ?string}>
     */
    private function customerOptions(ListSalesOrdersRequest $request, SaimsService $saims): array
    {
        if (! $request->user()->can('saims.customers')) {
            return [];
        }

        [$list] = $this->attempt(fn () => $saims->customers(['per_page' => SaimsService::MAX_PER_PAGE]));

        return collect($list['items'] ?? [])
            ->map(fn (array $c) => ['id' => $c['id'], 'name' => $c['customer_name']])
            ->all();
    }
}
