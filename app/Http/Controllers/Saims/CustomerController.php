<?php

namespace App\Http\Controllers\Saims;

use App\Http\Requests\Saims\ListCustomersRequest;
use App\Services\Saims\SaimsService;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends SaimsController
{
    protected function permission(): string
    {
        return 'saims.customers';
    }

    public function index(ListCustomersRequest $request, SaimsService $saims): Response
    {
        return $this->render($request, $saims);
    }

    public function show(ListCustomersRequest $request, SaimsService $saims, int $id): Response
    {
        $this->guard($request);

        [$record, $error] = $this->attempt(fn () => $saims->customerById($id), notFoundIs404: true);

        return $this->render($request, $saims, ['id' => $id, 'record' => $record, 'error' => $error]);
    }

    /**
     * @param  array{id: int, record: ?array<string, mixed>, error: ?array<string, string>}|null  $drawer
     */
    private function render(ListCustomersRequest $request, SaimsService $saims, ?array $drawer = null): Response
    {
        $this->guard($request);

        $filters = $request->filters();
        [$list, $error] = $this->attempt(fn () => $saims->customers($filters));

        return Inertia::render('Saims/Customers/Index', [
            'records' => $list,
            'filters' => $filters,
            'error' => $error,
            'drawer' => $drawer,
        ]);
    }
}
